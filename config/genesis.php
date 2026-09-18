<?php

return [
    'admin' => [
        'name' => env('GENESIS_ADMIN_NAME'),
        'username' => env('GENESIS_ADMIN_USERNAME'),
        'password' => env('GENESIS_ADMIN_PASSWORD'),
    ],
    'finance' => [
        'overview_cache_seconds' => env('FINANCE_OVERVIEW_CACHE_SECONDS', 300),
    ],
    'dashboard' => [
        'overview_cache_seconds' => env('DASHBOARD_OVERVIEW_CACHE_SECONDS', 300),
    ],
    'calendar' => [
        'timezone' => env('CALENDAR_TIMEZONE', 'America/Sao_Paulo'),
    ],
];
