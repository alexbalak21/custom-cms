<?php
// Front controller. Point your web server's document root to this folder.
require __DIR__ . '/../bootstrap.php';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . $uri)) return false;   // built-in server: serve static files
$router = new Router(rtrim(cfg('base_path', ''), '/'));
require ROOT . '/routes/admin.php';
require ROOT . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $uri);
