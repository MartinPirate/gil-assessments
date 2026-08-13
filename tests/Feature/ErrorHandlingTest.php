<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Error responses, which have to suit three very different audiences without
 * ever leaking internals.
 */
class ErrorHandlingTest extends TestCase
{
    public function test_it_maps_exceptions_to_the_right_status(): void
    {
        $this->assertSame(401, Handler::statusFor(new AuthenticationException));
        $this->assertSame(403, Handler::statusFor(new AuthorizationException));
        $this->assertSame(404, Handler::statusFor(new ModelNotFoundException));
        $this->assertSame(422, Handler::statusFor(ValidationException::withMessages(['a' => 'b'])));
        $this->assertSame(418, Handler::statusFor(new HttpException(418, 'teapot')));
        $this->assertSame(500, Handler::statusFor(new \RuntimeException('boom')));
    }

    /**
     * The single most important property: a 5xx message must never carry the
     * exception text, which routinely names tables, columns and file paths.
     */
    public function test_server_error_messages_are_generic(): void
    {
        $leaky = new QueryException(
            'sqlsrv',
            'select * from [secret_table] where [password] = ?',
            ['hunter2'],
            new \Exception('Invalid column name password'),
        );

        $message = Handler::safeMessage($leaky, 500);

        $this->assertStringNotContainsString('secret_table', $message);
        $this->assertStringNotContainsString('password', $message);
        $this->assertStringNotContainsString('hunter2', $message);
        $this->assertStringNotContainsString('select', $message);
        $this->assertSame('Something went wrong on our side. The team has been notified.', $message);
    }

    public function test_client_error_messages_stay_useful(): void
    {
        $this->assertSame('You do not have permission to do that.', Handler::safeMessage(new AuthorizationException, 403));
        $this->assertSame('Your session expired. Please refresh and try again.', Handler::safeMessage(new \Exception, 419));
        // An explicit HTTP message is the developer's own wording, so keep it.
        $this->assertSame('Nothing here', Handler::safeMessage(new NotFoundHttpException('Nothing here'), 404));
    }

    public function test_expected_exceptions_are_not_reported_as_incidents(): void
    {
        $this->assertFalse(Handler::shouldReport(ValidationException::withMessages(['a' => 'b'])));
        $this->assertFalse(Handler::shouldReport(new AuthenticationException));
        $this->assertFalse(Handler::shouldReport(new ModelNotFoundException));
        // 4xx is the caller's problem; only 5xx is ours.
        $this->assertFalse(Handler::shouldReport(new NotFoundHttpException));
        $this->assertTrue(Handler::shouldReport(new HttpException(500)));
        $this->assertTrue(Handler::shouldReport(new \RuntimeException('boom')));
    }

    public function test_a_reference_is_short_and_quotable(): void
    {
        $reference = Handler::reference();

        $this->assertSame(8, strlen($reference));
        $this->assertSame(strtoupper($reference), $reference);
        $this->assertNotSame($reference, Handler::reference());
    }

    public function test_mpesa_routes_are_recognised(): void
    {
        $this->assertTrue(Handler::isMpesaRoute(Request::create('/api/mpesa/c2b/confirmation', 'POST')));
        $this->assertTrue(Handler::isMpesaRoute(Request::create('/api/mpesa/c2b/validation', 'POST')));
        $this->assertFalse(Handler::isMpesaRoute(Request::create('/admin/invoices', 'GET')));
        $this->assertFalse(Handler::isMpesaRoute(Request::create('/api/other', 'POST')));
    }

    /**
     * An HTML error page would be read by Safaricom's parser as a failure and
     * the callback retried forever, so these must answer in ResultCode shape.
     */
    public function test_an_mpesa_route_answers_in_safaricom_shape(): void
    {
        // No TransID: rejected by validation, which owns its own response.
        $this->postJson('/api/mpesa/c2b/confirmation', ['TransAmount' => '10'])
            ->assertStatus(422)
            ->assertJsonPath('ResultCode', 1)
            ->assertJsonPath('success', false);
    }

    public function test_a_json_request_gets_json_not_html(): void
    {
        $this->getJson('/admin/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertJsonStructure(['success', 'message', 'reference'])
            ->assertJsonPath('success', false);
    }

    public function test_a_web_request_gets_the_branded_page(): void
    {
        // The custom page only takes over when debug is off, so a developer
        // still sees the real trace locally.
        config(['app.debug' => false]);

        $response = $this->get('/admin/this-route-does-not-exist');

        $response->assertStatus(404);
        $response->assertSee('GIL Business Suite', escape: false);
        $response->assertSee('404', escape: false);
    }

    public function test_the_error_page_shows_a_reference_only_for_server_errors(): void
    {
        config(['app.debug' => false]);

        $notFound = $this->get('/admin/this-route-does-not-exist');

        // A 404 is not an incident, so there is nothing to quote to support.
        $notFound->assertDontSee('Quote this reference');
    }
}
