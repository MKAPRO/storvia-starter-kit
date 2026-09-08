<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class CorsSecurityTest extends TestCase
{
    public function test_first_party_web_origin_receives_credentialed_cors_headers(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, X-XSRF-TOKEN',
        ])->options('/api/v1/auth/login')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_unapproved_origin_is_never_reflected_as_an_allowed_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/auth/login');

        // A CORS implementation may omit the header or emit the configured
        // first-party origin. The security invariant is that an unapproved
        // request origin must never be reflected as allowed.
        $this->assertNotSame(
            'https://evil.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_cors_configuration_never_uses_wildcard_origin_for_credentials(): void
    {
        $this->assertTrue((bool) config('cors.supports_credentials'));
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertContains('http://localhost:3000', config('cors.allowed_origins'));
    }
}
