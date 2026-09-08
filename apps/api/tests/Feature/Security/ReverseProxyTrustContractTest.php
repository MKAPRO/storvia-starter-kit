<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\TrustStorviaProxies;
use App\Support\Http\ReverseProxyConfiguration;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

class ReverseProxyTrustContractTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], 0);
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    public function test_local_default_does_not_trust_any_reverse_proxy_or_restrict_hosts(): void
    {
        config()->set('reverse_proxy.trusted_proxies', []);
        config()->set('reverse_proxy.trusted_hosts', []);

        $this->assertSame([], ReverseProxyConfiguration::trustedProxies());
        $this->assertSame([], ReverseProxyConfiguration::trustedHostPatterns());
    }

    public function test_catch_all_proxy_trust_is_rejected_fail_closed(): void
    {
        foreach (['*', '**', '0.0.0.0/0', '::/0', 'REMOTE_ADDR', 'private_ranges'] as $unsafe) {
            config()->set('reverse_proxy.trusted_proxies', [$unsafe]);

            try {
                ReverseProxyConfiguration::trustedProxies();
                $this->fail("Unsafe trusted proxy value [$unsafe] was accepted.");
            } catch (LogicException $exception) {
                $this->assertStringContainsString('refuses catch-all or implicit trusted proxy', $exception->getMessage());
            }
        }
    }

    public function test_proxy_entries_must_be_explicit_ip_addresses_or_valid_cidrs(): void
    {
        foreach (['proxy.internal', '10.0.0.0/not-a-prefix', '10.0.0.0/33', '2001:db8::/129'] as $invalid) {
            config()->set('reverse_proxy.trusted_proxies', [$invalid]);

            try {
                ReverseProxyConfiguration::trustedProxies();
                $this->fail("Invalid trusted proxy value [$invalid] was accepted.");
            } catch (LogicException $exception) {
                $this->assertStringContainsString('TRUSTED_PROXIES', $exception->getMessage());
            }
        }

        config()->set('reverse_proxy.trusted_proxies', ['10.0.0.10', '10.10.0.0/16', '2001:db8::1', '2001:db8::/64']);

        $this->assertSame(
            ['10.0.0.10', '10.10.0.0/16', '2001:db8::1', '2001:db8::/64'],
            ReverseProxyConfiguration::trustedProxies(),
        );
    }

    public function test_runtime_proxy_middleware_defers_config_reads_and_applies_only_explicit_trust(): void
    {
        config()->set('reverse_proxy.header_profile', 'x-forwarded');
        config()->set('reverse_proxy.trusted_proxies', []);

        $untrusted = Request::create('http://api.example.test/up', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.44',
            'HTTP_HOST' => 'api.example.test',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.25',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);

        (new TrustStorviaProxies)->handle($untrusted, static fn (Request $request): Request => $request);

        $this->assertSame('198.51.100.44', $untrusted->ip());
        $this->assertFalse($untrusted->isSecure());

        config()->set('reverse_proxy.trusted_proxies', ['10.10.10.10']);

        $trusted = Request::create('http://api.example.test/up', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.10.10.10',
            'HTTP_HOST' => 'api.example.test',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.25',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_HOST' => 'evil.example',
        ]);

        (new TrustStorviaProxies)->handle($trusted, static fn (Request $request): Request => $request);

        $this->assertSame('203.0.113.25', $trusted->ip());
        $this->assertTrue($trusted->isSecure());
        $this->assertSame('api.example.test', $trusted->getHost());
    }

    public function test_standard_profile_trusts_client_ip_https_and_port_but_not_forwarded_host(): void
    {
        config()->set('reverse_proxy.header_profile', 'x-forwarded');

        $headers = ReverseProxyConfiguration::trustedHeaders();

        $this->assertNotSame(0, $headers & Request::HEADER_X_FORWARDED_FOR);
        $this->assertNotSame(0, $headers & Request::HEADER_X_FORWARDED_PROTO);
        $this->assertNotSame(0, $headers & Request::HEADER_X_FORWARDED_PORT);
        $this->assertSame(0, $headers & Request::HEADER_X_FORWARDED_HOST);

        Request::setTrustedProxies(['10.10.10.10'], $headers);

        $request = Request::create('http://api.example.test/up', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.10.10.10',
            'HTTP_HOST' => 'api.example.test',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.25',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_HOST' => 'evil.example',
        ]);

        $this->assertSame('203.0.113.25', $request->ip());
        $this->assertTrue($request->isSecure());
        $this->assertSame(443, $request->getPort());
        $this->assertSame('api.example.test', $request->getHost());
    }

    public function test_aws_elb_profile_uses_the_framework_elb_mask_without_forwarded_host(): void
    {
        config()->set('reverse_proxy.header_profile', 'aws-elb');

        $headers = ReverseProxyConfiguration::trustedHeaders();

        $this->assertSame(Request::HEADER_X_FORWARDED_AWS_ELB, $headers);
        $this->assertSame(0, $headers & Request::HEADER_X_FORWARDED_HOST);
    }

    public function test_forwarded_headers_from_an_untrusted_client_are_ignored(): void
    {
        config()->set('reverse_proxy.header_profile', 'x-forwarded');
        Request::setTrustedProxies(['10.10.10.10'], ReverseProxyConfiguration::trustedHeaders());

        $request = Request::create('http://api.example.test/up', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.44',
            'HTTP_HOST' => 'api.example.test',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.25',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);

        $this->assertSame('198.51.100.44', $request->ip());
        $this->assertFalse($request->isSecure());
        $this->assertSame(80, $request->getPort());
    }

    public function test_trusted_hosts_are_exact_and_reject_host_header_injection(): void
    {
        config()->set('reverse_proxy.trusted_hosts', ['api.example.com']);
        $patterns = ReverseProxyConfiguration::trustedHostPatterns();

        $this->assertSame(['^api\\.example\\.com$'], $patterns);

        Request::setTrustedHosts($patterns);

        $accepted = Request::create('https://api.example.com/up');
        $this->assertSame('api.example.com', $accepted->getHost());

        $rejected = Request::create('https://evil.example/up');

        $this->expectException(SuspiciousOperationException::class);
        $rejected->getHost();
    }

    public function test_invalid_host_or_proxy_header_profile_is_rejected(): void
    {
        config()->set('reverse_proxy.trusted_hosts', ['https://api.example.com']);

        try {
            ReverseProxyConfiguration::trustedHostPatterns();
            $this->fail('Host with scheme was accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Invalid TRUSTED_HOSTS entry', $exception->getMessage());
        }

        config()->set('reverse_proxy.header_profile', 'trust-everything');

        $this->expectException(LogicException::class);
        ReverseProxyConfiguration::trustedHeaders();
    }
}
