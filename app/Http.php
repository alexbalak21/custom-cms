<?php
function view(string $name, array $d = []): void { extract($d); require ROOT . "/views/$name.php"; }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function json_out($d, int $code = 200): never { http_response_code($code); header('Content-Type: application/json'); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function admin_view(string $view, ?string $id = null, int $code = 200): void { http_response_code($code); view('admin/layout', ['view' => $view, 'id' => $id, 'user' => Auth::user()]); }
function not_found(): void {
    http_response_code(404);
    echo ($nf = Pages::find('', '404')) ? Renderer::page($nf) : Renderer::notFound();   // create a page with slug "404" to customise it
}
