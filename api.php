<?php
require __DIR__ . '/lib.php';
$in = json_decode(file_get_contents('php://input'), true) ?? [];
$a = $_GET['action'] ?? '';
if ($a === 'render') { // returns HTML for iframe srcdoc
    header('Content-Type: text/html; charset=utf-8');
    echo isset($in['block']) ? Renderer::block($in['block']) : Renderer::page($in['page'] ?? []);
    exit;
}
header('Content-Type: application/json');
$out = match ($a) {
    'blocks'       => Store::all('blocks'),
    'pages'        => Store::all('pages'),
    'save_block'   => Store::save('blocks', $in),
    'save_page'    => Store::save('pages', $in),
    'delete_block' => (function () use ($in) { Store::delete('blocks', $in['id'] ?? ''); return ['ok' => true]; })(),
    'delete_page'  => (function () use ($in) { Store::delete('pages', $in['id'] ?? ''); return ['ok' => true]; })(),
    'compile'      => ['file' => Renderer::compile($in['page'] ?? [])],
    default        => ['error' => 'unknown action'],
};
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);