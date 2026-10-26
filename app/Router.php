<?php
// Tiny Laravel-style router: $router->get('/pages/{id}/edit', $handler, [$middleware]);  {name} or {name:regex}
class Router {
    private array $routes = []; private $fallback;
    function __construct(private string $base = '') {}
    function add(string $method, string $pattern, callable $h, array $mw = []): void {
        $re = preg_replace_callback('/\{(\w+)(?::([^}]+))?\}|([^{]+)/', fn($m) => isset($m[3]) ? preg_quote($m[3], '#') : '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')', $pattern);
        $this->routes[] = [$method, "#^$re$#", $h, $mw];
    }
    function get(string $p, callable $h, array $mw = []): void { $this->add('GET', $p, $h, $mw); }
    function post(string $p, callable $h, array $mw = []): void { $this->add('POST', $p, $h, $mw); }
    function fallback(callable $h): void { $this->fallback = $h; }
    function dispatch(string $method, string $uri): void {
        $path = '/' . trim(rawurldecode(substr($uri, strlen($this->base))), '/'); $allowed = [];
        foreach ($this->routes as [$m, $re, $h, $mw]) {
            if (!preg_match($re, $path, $mt)) continue;
            if ($m !== $method) { $allowed[] = $m; continue; }
            foreach ($mw as $f) if ($f() === false) return;
            $h(array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY)); return;
        }
        if ($allowed) { http_response_code(405); header('Allow: ' . implode(', ', array_unique($allowed))); echo 'Method Not Allowed'; return; }
        ($this->fallback)();
    }
}
