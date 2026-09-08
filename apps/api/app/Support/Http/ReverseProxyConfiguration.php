<?php

namespace App\Support\Http;

use Illuminate\Http\Request;
use LogicException;

final class ReverseProxyConfiguration
{
    /**
     * Values that would allow an arbitrary internet client to become a
     * "trusted proxy" when the origin is directly reachable.
     */
    private const UNSAFE_CATCH_ALL_PROXIES = [
        '*',
        '**',
        '0.0.0.0/0',
        '::/0',
        'remote_addr',
        'private_subnets',
        'private_ranges',
    ];

    public static function trustedProxies(): array
    {
        $proxies = self::normalizeList(config('reverse_proxy.trusted_proxies', []));

        foreach ($proxies as $proxy) {
            self::validateProxy($proxy);
        }

        return $proxies;
    }

    public static function trustedHeaders(): int
    {
        $profile = strtolower(trim((string) config('reverse_proxy.header_profile', 'x-forwarded')));

        return match ($profile) {
            'x-forwarded' => Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT,
            'aws-elb' => Request::HEADER_X_FORWARDED_AWS_ELB,
            default => throw new LogicException(sprintf(
                'Unsupported TRUSTED_PROXY_HEADER_PROFILE [%s]. Allowed values: x-forwarded, aws-elb.',
                $profile,
            )),
        };
    }

    public static function trustedHostPatterns(): array
    {
        return array_map(
            static fn (string $host): string => '^'.preg_quote(self::validateExactHost($host), '#').'$',
            self::normalizeList(config('reverse_proxy.trusted_hosts', [])),
        );
    }

    private static function normalizeList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);
        $items = array_map(static fn (mixed $item): string => trim((string) $item), $items);

        return array_values(array_unique(array_filter(
            $items,
            static fn (string $item): bool => $item !== '',
        )));
    }

    private static function validateProxy(string $proxy): void
    {
        $normalized = strtolower(trim($proxy));

        if (in_array($normalized, self::UNSAFE_CATCH_ALL_PROXIES, true)) {
            throw new LogicException(
                'STORVIA refuses catch-all or implicit trusted proxy configuration. Configure an explicit proxy IP/CIDR that connects to Laravel.',
            );
        }

        [$address, $prefix] = array_pad(explode('/', $proxy, 2), 2, null);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            throw new LogicException(sprintf(
                'Invalid TRUSTED_PROXIES entry [%s]. Use an explicit IP address or CIDR range.',
                $proxy,
            ));
        }

        if ($prefix === null) {
            return;
        }

        if ($prefix === '' || ! ctype_digit($prefix)) {
            throw new LogicException(sprintf('Invalid TRUSTED_PROXIES CIDR [%s].', $proxy));
        }

        $maxPrefix = str_contains($address, ':') ? 128 : 32;
        $prefixLength = (int) $prefix;

        if ($prefixLength < 0 || $prefixLength > $maxPrefix) {
            throw new LogicException(sprintf('Invalid TRUSTED_PROXIES CIDR [%s].', $proxy));
        }
    }

    private static function validateExactHost(string $host): string
    {
        $host = trim($host);

        if (
            $host === ''
            || str_contains($host, '://')
            || str_contains($host, '/')
            || str_contains($host, '\\')
            || str_contains($host, '*')
            || preg_match('/\s/u', $host) === 1
            || str_contains($host, ':')
        ) {
            throw new LogicException(sprintf(
                'Invalid TRUSTED_HOSTS entry [%s]. Use exact hostnames without scheme, port, path, whitespace, or wildcard syntax.',
                $host,
            ));
        }

        return $host;
    }
}
