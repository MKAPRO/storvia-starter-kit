<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class ApiSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        foreach (self::baseValues() as $name => $value) {
            $response->headers->set($name, $value);
        }

        $hsts = $this->strictTransportSecurityValue($request);

        if ($hsts !== null) {
            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    public static function baseValues(): array
    {
        return [
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ];
    }

    private function strictTransportSecurityValue(Request $request): ?string
    {
        if (
            config('app.env') !== 'production'
            || ! config('security_headers.hsts.enabled', false)
            || ! $request->isSecure()
        ) {
            return null;
        }

        $maxAge = config('security_headers.hsts.max_age', 31536000);

        if (! is_int($maxAge) && ! (is_string($maxAge) && ctype_digit($maxAge))) {
            throw new LogicException('STORVIA_HSTS_MAX_AGE must be a positive integer when HSTS is enabled.');
        }

        $maxAge = (int) $maxAge;

        if ($maxAge <= 0) {
            throw new LogicException('STORVIA_HSTS_MAX_AGE must be greater than zero when HSTS is enabled.');
        }

        $value = 'max-age='.$maxAge;

        if (config('security_headers.hsts.include_subdomains', false)) {
            $value .= '; includeSubDomains';
        }

        return $value;
    }
}
