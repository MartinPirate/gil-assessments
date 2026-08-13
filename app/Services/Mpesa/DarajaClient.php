<?php

namespace App\Services\Mpesa;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Talks to Safaricom Daraja.
 *
 * The C2B callback endpoints are useless until Safaricom has been told where
 * to send traffic, and that registration is an authenticated API call — so the
 * integration needs an OAuth token and a RegisterURL step, not just routes.
 */
class DarajaClient
{
    /**
     * OAuth token, cached because Daraja issues short-lived tokens and rate
     * limits the auth endpoint. Expiry is shaved by a minute so a token is
     * never used in the second it lapses.
     */
    public function accessToken(bool $fresh = false): string
    {
        $key = 'mpesa.access_token.'.$this->environment();

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(50), function (): string {
            $consumerKey = $this->config('consumer_key');
            $consumerSecret = $this->config('consumer_secret');

            $response = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->timeout(30)
                ->get($this->baseUrl().'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

            if ($response->failed() || blank($response->json('access_token'))) {
                Log::error('Daraja OAuth failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new RuntimeException('Could not obtain an M-Pesa access token: '.$response->body());
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * Register the Validation and Confirmation URLs for the short code.
     *
     * ResponseType governs what Safaricom does when the Validation URL cannot
     * be reached: "Completed" accepts the payment anyway, "Cancelled" rejects
     * it. Defaulting to Completed means an outage never turns paying customers
     * away — the receipt is reconciled afterwards instead.
     *
     * @return array<string, mixed>
     */
    public function registerUrls(?string $confirmationUrl = null, ?string $validationUrl = null): array
    {
        $payload = [
            'ShortCode' => $this->config('shortcode'),
            'ResponseType' => $this->config('response_type', 'Completed'),
            'ConfirmationURL' => $confirmationUrl ?? route('mpesa.c2b.confirmation'),
            'ValidationURL' => $validationUrl ?? route('mpesa.c2b.validation'),
        ];

        // Safaricom silently drops registrations pointing at unreachable hosts,
        // which then looks like "the callback never fires". Fail loudly here.
        foreach (['ConfirmationURL', 'ValidationURL'] as $field) {
            if (str_contains($payload[$field], 'localhost') || str_contains($payload[$field], '127.0.0.1')) {
                throw new RuntimeException(
                    "{$field} is {$payload[$field]} — Safaricom cannot reach a local address. ".
                    'Set APP_URL to a publicly reachable host (or a tunnel) before registering.'
                );
            }
        }

        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->post($this->baseUrl().'/mpesa/c2b/v1/registerurl', $payload);

        $body = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('C2B URL registration failed', ['status' => $response->status(), 'body' => $body]);

            return ['success' => false, 'error' => $body['errorMessage'] ?? $response->body(), 'sent' => $payload];
        }

        Log::info('C2B URLs registered with Safaricom', $payload);

        return ['success' => true, 'data' => $body, 'sent' => $payload];
    }

    /**
     * Whether the credentials needed to talk to Daraja are present.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $missing = collect(['consumer_key', 'consumer_secret', 'shortcode'])
            ->filter(fn (string $key) => blank(config("services.mpesa.{$key}")))
            ->values()
            ->all();

        return [
            'environment' => $this->environment(),
            'base_url' => $this->baseUrl(),
            'shortcode' => config('services.mpesa.shortcode') ?: null,
            'confirmation_url' => route('mpesa.c2b.confirmation'),
            'validation_url' => route('mpesa.c2b.validation'),
            'missing_config' => $missing,
            'configured' => $missing === [],
        ];
    }

    public function environment(): string
    {
        return config('services.mpesa.environment', 'sandbox') === 'production'
            ? 'production'
            : 'sandbox';
    }

    public function baseUrl(): string
    {
        return $this->environment() === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    protected function config(string $key, ?string $default = null): string
    {
        $value = config("services.mpesa.{$key}", $default);

        if (blank($value)) {
            throw new RuntimeException("M-Pesa config [services.mpesa.{$key}] is not set.");
        }

        return (string) $value;
    }
}
