<?php
require __DIR__ . '/../lib.php';
Auth::start();
if (!Auth::user()) { http_response_code(401); header('Content-Type: application/json'); echo '{"error":"Not authenticated"}'; exit; }
$a = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(Auth::csrf(), $_SERVER['HTTP_X_CSRF'] ?? '')) { http_response_code(403); header('Content-Type: application/json'); echo '{"error":"Bad CSRF token"}'; exit; }
$in = json_decode(file_get_contents('php://input'), true) ?? [];
if ($a === 'render') { echo isset($in['block']) ? Renderer::block($in['block']) : Renderer::page($in['page'] ?? []); exit; }
header('Content-Type: application/json');
try {
    $out = match ($a) {
        'me'            => ['user' => Auth::user(), 'default_pw' => Auth::isDefault(), 'db' => Db::pdo() !== null, 'procedures' => array_keys(Rpc::procs())],
        'blocks'        => Store::all('blocks'),
        'pages'         => Store::all('pages'),
        'categories'    => Store::json('categories', []),
        'site'          => Store::json('site', []),
        'media'         => Media::list(),
        'save_block'    => isset($in['template']) ? Store::save('blocks', $in) : throw new RuntimeException('Invalid block'),
        'save_page'     => Pages::save($in),
        'delete_block'  => (function () use ($in) { Store::delete('blocks', $in['id'] ?? ''); return ['ok' => true]; })(),
        'delete_page'   => (function () use ($in) { Store::delete('pages', $in['id'] ?? ''); return ['ok' => true]; })(),
        'save_categories' => (function () use ($in) {
            $c = []; foreach ($in as $x) if ($s = slugify($x['slug'] ?? '')) $c[] = ['slug' => $s, 'title' => trim($x['title'] ?? '') ?: $s, 'description' => $x['description'] ?? ''];
            Store::put('categories', $c); return $c; })(),
        'save_site'     => (function () use ($in) { $s = ['name' => trim($in['name'] ?? '') ?: 'Site', 'base_url' => rtrim($in['base_url'] ?? '', '/')]; Store::put('site', $s); return $s; })(),
        'password'      => (function () use ($in) { Auth::changePassword($in['password'] ?? ''); return ['ok' => true]; })(),
        'upload'        => Media::upload($_FILES['files'] ?? ['name' => []]),
        'media_update'  => Media::update($in['id'] ?? '', $in),
        'media_delete'  => (function () use ($in) { Media::delete($in['id'] ?? ''); return ['ok' => true]; })(),
        'compile_all'   => Renderer::compileAll(),
        default         => throw new RuntimeException('Unknown action'),
    };
} catch (RuntimeException $e) { http_response_code(400); $out = ['error' => $e->getMessage()]; }
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
