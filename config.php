<?php
// All values come from .env
return [
    'base_path' => env('APP_BASE_PATH', ''),
    'base_url'  => env('APP_URL', 'http://localhost:8000'),
    'site_name' => env('APP_NAME', 'Custom CMS'),
    'db' => [
        'host'     => env('DB_HOST', '127.0.0.1'),
        'port'     => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', ''),
        'username' => env('DB_USERNAME', ''),
        'password' => env('DB_PASSWORD', ''),
    ],
];
