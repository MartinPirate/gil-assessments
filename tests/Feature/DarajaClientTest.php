<?php

namespace Tests\Feature;

use App\Services\Mpesa\DarajaClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Registering the C2B callback URLs with Safaricom.
 *
 * The HTTP layer is faked throughout — these tests must never reach Daraja.
 */
class DarajaClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Config::set('services.mpesa.consumer_key', 'test-key');
        Config::set('services.mpesa.consumer_secret', 'test-secret');
        Config::set('services.mpesa.shortcode', '600984');
        Config::set('services.mpesa.environment', 'sandbox');
        Config::set('app.url', 'https://gil.example.com');
        // route() builds absolute URLs from this.
        url()->forceRootUrl('https://gil.example.com');
    }

    public function test_it_uses_the_sandbox_host_by_default(): void
    {
        $this->assertSame('https://sandbox.safaricom.co.ke', app(DarajaClient::class)->baseUrl());
    }

    public function test_it_switches_to_the_production_host(): void
    {
        Config::set('services.mpesa.environment', 'production');

        $this->assertSame('https://api.safaricom.co.ke', app(DarajaClient::class)->baseUrl());
    }

    public function test_it_fetches_and_caches_an_access_token(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok-123', 'expires_in' => '3599']),
        ]);

        $client = app(DarajaClient::class);

        $this->assertSame('tok-123', $client->accessToken());
        $this->assertSame('tok-123', $client->accessToken());

        // Daraja rate limits the auth endpoint, so the second call must be
        // served from cache.
        Http::assertSentCount(1);
    }

    public function test_a_failed_token_request_raises_rather_than_returning_empty(): void
    {
        Http::fake(['*/oauth/v1/generate*' => Http::response(['errorMessage' => 'Bad credentials'], 401)]);

        $this->expectException(RuntimeException::class);

        app(DarajaClient::class)->accessToken();
    }

    public function test_it_registers_both_urls_against_the_short_code(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok-123']),
            '*/mpesa/c2b/v1/registerurl' => Http::response([
                'OriginatorCoversationID' => '123',
                'ResponseDescription' => 'Success',
            ]),
        ]);

        $result = app(DarajaClient::class)->registerUrls();

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'registerurl')) {
                return false;
            }

            return $request['ShortCode'] === '600984'
                && $request['ResponseType'] === 'Completed'
                && str_ends_with($request['ConfirmationURL'], '/api/mpesa/c2b/confirmation')
                && str_ends_with($request['ValidationURL'], '/api/mpesa/c2b/validation');
        });
    }

    public function test_a_registration_error_is_reported_not_thrown(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok-123']),
            '*/mpesa/c2b/v1/registerurl' => Http::response(['errorMessage' => 'Invalid shortcode'], 400),
        ]);

        $result = app(DarajaClient::class)->registerUrls();

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid shortcode', $result['error']);
    }

    /**
     * Safaricom accepts a registration pointing at localhost and then never
     * delivers, which presents as "the callback never fires". Fail loudly.
     */
    public function test_it_refuses_to_register_an_unreachable_local_url(): void
    {
        Http::fake(['*/oauth/v1/generate*' => Http::response(['access_token' => 'tok-123'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Safaricom cannot reach a local address');

        app(DarajaClient::class)->registerUrls('http://localhost/api/mpesa/c2b/confirmation');
    }

    public function test_status_reports_missing_configuration(): void
    {
        Config::set('services.mpesa.shortcode', null);

        $status = app(DarajaClient::class)->status();

        $this->assertFalse($status['configured']);
        $this->assertContains('shortcode', $status['missing_config']);
    }

    public function test_the_register_command_refuses_without_credentials(): void
    {
        Config::set('services.mpesa.consumer_key', null);

        $this->artisan('mpesa:register-urls')
            ->assertExitCode(1);
    }

    public function test_the_register_command_can_be_aborted(): void
    {
        Http::fake();

        $this->artisan('mpesa:register-urls')
            ->expectsConfirmation(
                'Register these URLs against short code 600984 on sandbox?',
                'no',
            )
            ->assertExitCode(0);

        // Nothing may reach Safaricom when the operator declines.
        Http::assertNothingSent();
    }
}
