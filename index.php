<?php
// Public front controller / router
require __DIR__ . '/lib.php';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (PHP_SAPI === 'cli-server' && preg_match('#^/(admin|uploads)(/|$)#', $uri) && (is_file(__DIR__ . $uri) || is_dir(__DIR__ . $uri))) return false;
$path = trim(substr($uri, strlen(rtrim(cfg('base_path', ''), '/'))), '/');
$seg = $path === '' ? [] : array_map('rawurldecode', explode('/', $path));
$site = Store::json('site', []) ?: ['name' => cfg('site_name'), 'base_url' => cfg('base_url')];
$base = rtrim($site['base_url'] ?? cfg('base_url'), '/');

if (($seg[0] ?? '') === 'rpc' && isset($seg[1])) {            // /rpc/products.list?category=x
    header('Content-Type: application/json');
    $proc = Rpc::procs()[$seg[1]] ?? null;
    if (!$proc || empty($proc['public'])) { http_response_code(404); echo '{"error":"unknown procedure"}'; exit; }
    $params = $_GET + (json_decode(file_get_contents('php://input'), true) ?: []);
    echo json_encode(Rpc::call($seg[1], $params), JSON_UNESCAPED_UNICODE); exit;
}
if ($path === 'robots.txt') { header('Content-Type: text/plain'); echo "User-agent: *\nDisallow: /admin/\nSitemap: $base/sitemap.xml\n"; exit; }
if ($path === 'sitemap.xml') {
    header('Content-Type: application/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach (Store::all('pages') as $p) if (($p['status'] ?? '') === 'published' && empty($p['seo']['noindex'])) echo '<url><loc>' . e($base . '/' . Pages::path($p)) . '</loc><lastmod>' . e(substr($p['updated_at'] ?? gmdate('c'), 0, 10)) . '</lastmod></url>';
    foreach (Store::json('categories', []) as $c) echo '<url><loc>' . e("$base/{$c['slug']}") . '</loc></url>';
    echo '</urlset>'; exit;
}

$drafts = false;                                                // logged-in admins may preview drafts with ?preview=1
if (isset($_GET['preview']) && isset($_COOKIE['custom_cms'])) { Auth::start(); $drafts = (bool)Auth::user(); }
$page = match (count($seg)) { 0 => Pages::find('', '', $drafts), 1 => Pages::find('', $seg[0], $drafts), 2 => Pages::find($seg[0], $seg[1], $drafts), default => null };
if ($page) { echo Renderer::page($page); exit; }
if (count($seg) === 1) foreach (Store::json('categories', []) as $c) if ($c['slug'] === $seg[0]) { echo Renderer::category($c); exit; }
http_response_code(404);
echo ($nf = Pages::find('', '404')) ? Renderer::page($nf) : Renderer::notFound();
