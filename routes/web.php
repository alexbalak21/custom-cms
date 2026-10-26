<?php
// Public routes
$serve = function (string $cat, string $slug): bool {
    $drafts = isset($_GET['preview']) && Auth::user() !== null;          // admins can preview drafts with ?preview=1
    if ($page = Pages::find($cat, $slug, $drafts)) { echo Renderer::page($page); return true; }
    if ($cat === '' && $slug !== '') foreach (Store::json('categories', []) as $c) if ($c['slug'] === $slug) { echo Renderer::category($c); return true; }
    return false;
};
$rpc = function (array $p) {
    $proc = Rpc::procs()[$p['name']] ?? null;
    if (!$proc || empty($proc['public'])) json_out(['error' => 'unknown procedure'], 404);
    json_out(Rpc::call($p['name'], $_GET + (json_decode(file_get_contents('php://input'), true) ?: [])));
};
$router->get('/rpc/{name}', $rpc); $router->post('/rpc/{name}', $rpc);
$router->get('/robots.txt', function () { header('Content-Type: text/plain'); echo "User-agent: *\nDisallow: /admin/\nSitemap: " . rtrim(Store::json('site', [])['base_url'] ?? cfg('base_url'), '/') . "/sitemap.xml\n"; });
$router->get('/sitemap.xml', function () {
    $base = rtrim(Store::json('site', [])['base_url'] ?? cfg('base_url'), '/'); header('Content-Type: application/xml');
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach (Store::all('pages') as $p) if (($p['status'] ?? '') === 'published' && empty($p['seo']['noindex'])) echo '<url><loc>' . e($base . '/' . Pages::path($p)) . '</loc><lastmod>' . e(substr($p['updated_at'] ?? gmdate('c'), 0, 10)) . '</lastmod></url>';
    foreach (Store::json('categories', []) as $c) echo '<url><loc>' . e("$base/{$c['slug']}") . '</loc></url>';
    echo '</urlset>';
});
$router->get('/',                     fn() => $serve('', '') || not_found());
$router->get('/{slug}',               fn($p) => $serve('', $p['slug']) || not_found());
$router->get('/{category}/{slug}',    fn($p) => $serve($p['category'], $p['slug']) || not_found());
$router->fallback('not_found');       // any other URL / depth -> 404 page
