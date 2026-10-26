<?php
function admin_api(string $a): void {
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    if ($a === 'render') { echo isset($in['block']) ? Renderer::block($in['block']) : Renderer::page($in['page'] ?? []); return; }
    try {
        $out = match ($a) {
            'me'            => ['user' => Auth::user(), 'db' => Db::pdo() !== null, 'procedures' => array_keys(Rpc::procs())],
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
            'password'      => (function () use ($in) { Auth::setPassword((int)Auth::user()['id'], $in['password'] ?? '', true); return ['ok' => true]; })(),
            'upload'        => Media::upload($_FILES['files'] ?? ['name' => []]),
            'media_update'  => Media::update($in['id'] ?? '', $in),
            'media_delete'  => (function () use ($in) { Media::delete($in['id'] ?? ''); return ['ok' => true]; })(),
            'compile_all'   => Renderer::compileAll(),
            default         => throw new RuntimeException('Unknown action'),
        };
    } catch (RuntimeException $e) { json_out(['error' => $e->getMessage()], 400); }
    json_out($out);
}
