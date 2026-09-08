<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiErrorSecurityHeadersRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/testing/security-headers-forbidden', static function () {
            abort(403);
        });
    }

    public function test_unauthenticated_api_error_response_receives_security_header_baseline(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'")
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_forbidden_and_not_found_api_error_responses_receive_security_header_baseline(): void
    {
        foreach (['/api/testing/security-headers-forbidden', '/api/testing/security-headers-missing'] as $uri) {
            $response = $this->getJson($uri);

            $this->assertContains($response->status(), [403, 404]);

            $response
                ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'")
                ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
                ->assertHeader('Referrer-Policy', 'no-referrer')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY');
        }
    }

    public function test_global_api_security_wrapper_leaves_non_api_response_unmodified(): void
    {
        Route::get('/testing/non-api-security-header-probe', static fn () => response('ok'));

        $this->get('/testing/non-api-security-header-probe')
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeaderMissing('Permissions-Policy')
            ->assertHeaderMissing('X-Frame-Options');
    }
}
