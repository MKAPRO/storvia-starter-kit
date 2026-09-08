<?php

$csv = static function (mixed $value): array {
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = explode(',', (string) $value);
    }

    $items = array_map(
        static fn (mixed $item): string => trim((string) $item),
        $items,
    );

    return array_values(array_unique(array_filter(
        $items,
        static fn (string $item): bool => $item !== '',
    )));
};

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted reverse proxies
    |--------------------------------------------------------------------------
    |
    | Explicit IP addresses / CIDR ranges of reverse proxies that connect
    | directly to Laravel. Leave empty for direct/local development traffic.
    | STORVIA deliberately rejects catch-all proxy trust in production code.
    |
    */
    'trusted_proxies' => $csv(env('TRUSTED_PROXIES', '')),

    /*
    |--------------------------------------------------------------------------
    | Trusted proxy header profile
    |--------------------------------------------------------------------------
    |
    | x-forwarded trusts X-Forwarded-For / Proto / Port but intentionally does
    | not trust X-Forwarded-Host. aws-elb uses Symfony's AWS ELB header mask.
    |
    */
    'header_profile' => strtolower(trim((string) env('TRUSTED_PROXY_HEADER_PROFILE', 'x-forwarded'))),

    /*
    |--------------------------------------------------------------------------
    | Trusted application hosts
    |--------------------------------------------------------------------------
    |
    | Exact hostnames only (no schemes, ports, paths, or wildcard syntax).
    | Leave empty to preserve the existing local/XAMPP behavior.
    |
    */
    'trusted_hosts' => $csv(env('TRUSTED_HOSTS', '')),
];
