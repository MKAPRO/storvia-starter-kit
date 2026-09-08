<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticatedAppSecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/testing/security-headers-probe', static fn () => response()->json([
            'ok' => true,
        ]));
    }

    public function test_api_group_applies_the_browser_security_header_baseline(): void
    {
        $this->getJson('/api/testing/security-headers-probe')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'")
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_never_emitted_for_http_even_when_production_enabled(): void
    {
        config()->set('app.env', 'production');
        config()->set('security_headers.hsts.enabled', true);
        config()->set('security_headers.hsts.max_age', 31536000);

        $this->getJson('/api/testing/security-headers-probe')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_emitted_only_for_secure_production_requests_when_explicitly_enabled(): void
    {
        config()->set('app.env', 'production');
        config()->set('security_headers.hsts.enabled', true);
        config()->set('security_headers.hsts.max_age', 31536000);
        config()->set('security_headers.hsts.include_subdomains', false);

        $this->getJson('https://localhost/api/testing/security-headers-probe')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_hsts_respects_the_harden_02_trusted_proxy_https_contract(): void
    {
        config()->set('app.env', 'production');
        config()->set('security_headers.hsts.enabled', true);
        config()->set('security_headers.hsts.max_age', 31536000);
        config()->set('security_headers.hsts.include_subdomains', false);
        config()->set('reverse_proxy.header_profile', 'x-forwarded');
        config()->set('reverse_proxy.trusted_proxies', ['10.10.10.10']);

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.10.10.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.25',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->getJson('/api/testing/security-headers-probe')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_hsts_include_subdomains_requires_explicit_opt_in(): void
    {
        config()->set('app.env', 'production');
        config()->set('security_headers.hsts.enabled', true);
        config()->set('security_headers.hsts.max_age', 31536000);
        config()->set('security_headers.hsts.include_subdomains', true);

        $this->getJson('https://localhost/api/testing/security-headers-probe')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
