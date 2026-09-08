<?php

return [
    'hsts' => [
        'enabled' => filter_var(env('STORVIA_HSTS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'max_age' => env('STORVIA_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => filter_var(env('STORVIA_HSTS_INCLUDE_SUBDOMAINS', false), FILTER_VALIDATE_BOOL),
    ],
];
