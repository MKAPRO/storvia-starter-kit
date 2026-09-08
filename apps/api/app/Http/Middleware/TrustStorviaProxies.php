<?php

namespace App\Http\Middleware;

use App\Support\Http\ReverseProxyConfiguration;
use Closure;
use Illuminate\Http\Request;

final class TrustStorviaProxies
{
    public function handle(Request $request, Closure $next): mixed
    {
        $headers = ReverseProxyConfiguration::trustedHeaders();

        // Reset Symfony's static trusted-proxy state for long-running workers/tests.
        Request::setTrustedProxies([], $headers);

        $trustedProxies = ReverseProxyConfiguration::trustedProxies();

        if ($trustedProxies !== []) {
            Request::setTrustedProxies($trustedProxies, $headers);
        }

        return $next($request);
    }
}
