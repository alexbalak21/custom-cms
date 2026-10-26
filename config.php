<?php
// Custom CMS configuration
return [
    'base_path'  => '',                       // '' if installed at web root, '/mysite' if in a subfolder
    'base_url'   => 'http://localhost:8000',  // used for canonical, og:image, sitemap (also editable in Settings)
    'site_name'  => 'Custom CMS',
    'admin_user' => 'admin',                  // first-run credentials (stored hashed in data/users.json)
    'admin_pass' => 'changeme',               // CHANGE IT in Admin > Settings after first login
    // MySQL for product blocks. Leave dsn empty to use the built-in sample data.
    'db' => [
        'dsn'  => '',  // e.g. 'mysql:host=localhost;dbname=shop;charset=utf8mb4'
        'user' => '',
        'pass' => '',
    ],
];
