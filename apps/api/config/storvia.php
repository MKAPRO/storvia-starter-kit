<?php

return [
    'setup_token' => env('STORVIA_SETUP_TOKEN'),

    'starter_demo' => [
        'enabled' => filter_var(
            env('STORVIA_STARTER_DEMO_SEED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'admin_password' => env('STORVIA_STARTER_DEMO_PASSWORD'),
    ],
];
