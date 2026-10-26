<?php
// Admin routes ($router is provided by public/index.php)
$auth    = function () { if (Auth::user()) return true; redirect(url('admin/login')); };
$apiAuth = function () { if (Auth::user()) return true; json_out(['error' => 'Not authenticated'], 401); };
$csrf    = function () { if (hash_equals(Auth::csrf(), $_SERVER['HTTP_X_CSRF'] ?? ($_POST['csrf'] ?? ''))) return true; json_out(['error' => 'Bad CSRF token. Reload the page.'], 403); };

$router->get('/admin/login', function () { if (Auth::user()) redirect(url('admin')); view('admin/login', ['err' => '']); });
$router->post('/admin/login', function () {
    try { $err = Auth::login(trim($_POST['username'] ?? ''), $_POST['password'] ?? ''); }
    catch (Throwable $e) { $err = $e instanceof RuntimeException ? $e->getMessage() : 'Database error. Did you run: php cms migrate ?'; }
    if (!$err) redirect(url('admin'));
    http_response_code(401); view('admin/login', ['err' => $err]);
}, [$csrf]);
$router->post('/admin/logout', function () { Auth::logout(); redirect(url('admin/login')); }, [$csrf]);

// Screens (each renders the admin shell; public/assets/admin.js draws the view)
$screen = fn(string $v) => fn(array $p) => admin_view($v, $p['id'] ?? null);
$router->get('/admin',                    $screen('dashboard'),  [$auth]);
$router->get('/admin/pages',              $screen('pages'),      [$auth]);
$router->get('/admin/pages/create',       $screen('page'),       [$auth]);
$router->get('/admin/pages/{id}/edit',    $screen('page'),       [$auth]);
$router->get('/admin/blocks',             $screen('blocks'),     [$auth]);
$router->get('/admin/blocks/{id}',        $screen('blocks'),     [$auth]);
$router->get('/admin/media',              $screen('media'),      [$auth]);
$router->get('/admin/categories',         $screen('categories'), [$auth]);
$router->get('/admin/settings',           $screen('settings'),   [$auth]);

// JSON API used by the admin screens
$router->get('/admin/api/{action}',  fn($p) => admin_api($p['action']), [$apiAuth]);
$router->post('/admin/api/{action}', fn($p) => admin_api($p['action']), [$apiAuth, $csrf]);

// Admin 404 (anything else under /admin)
$router->get('/admin/{any:.*}', function () { if (Auth::user()) admin_view('404', null, 404); else { http_response_code(404); view('admin/login', ['err' => '']); } });
