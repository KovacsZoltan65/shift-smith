<?php

return [
    'default_users' => env('SEED_DEFAULT_USERS', false),

    // PROD policy
    'prod_only_superadmin' => env('SEED_PROD_ONLY_SUPERADMIN', true),

    'reset_passwords'         => env('SEED_RESET_PASSWORDS', false),
    'generate_passwords'      => env('SEED_GENERATE_PASSWORDS', true),
    'log_generated_passwords' => env('SEED_LOG_GENERATED_PASSWORDS', true),

    'superadmin_email'    => env('SEED_SUPERADMIN_EMAIL', 'superadmin@shift-smith.com'),
    'superadmin_password' => env('SEED_SUPERADMIN_PASSWORD'),

    'admin_email'    => env('SEED_ADMIN_EMAIL', 'admin@shift-smith.com'),
    'admin_password' => env('SEED_ADMIN_PASSWORD'),

    'operator_email'    => env('SEED_OPERATOR_EMAIL', 'operator@shift-smith.com'),
    'operator_password' => env('SEED_OPERATOR_PASSWORD'),

    'user_email'    => env('SEED_USER_EMAIL', 'user@shift-smith.com'),
    'user_password' => env('SEED_USER_PASSWORD'),
];