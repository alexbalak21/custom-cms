<?php
define('ROOT', __DIR__);
function env(string $k, $d = null) { $v = $_ENV[$k] ?? getenv($k); return ($v === false || $v === null || $v === '') ? $d : $v; }
$f = ROOT . '/.env';
if (is_file($f)) foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    $l = trim($l); if ($l === '' || $l[0] === '#' || !str_contains($l, '=')) continue;
    [$k, $v] = array_map('trim', explode('=', $l, 2));
    if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
    $_ENV[$k] = $v; putenv("$k=$v");
}
$GLOBALS['CFG'] = require ROOT . '/config.php';
function cfg(string $k, $d = null) { return $GLOBALS['CFG'][$k] ?? $d; }
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES); }
function url(string $p = ''): string { return rtrim(cfg('base_path', ''), '/') . '/' . ltrim($p, '/'); }
function slugify(string $s): string { $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s; return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-'); }
foreach (['Core', 'Auth', 'Router', 'Migrator', 'Http', 'Mailer', 'Form', 'AdminApi'] as $c) require ROOT . "/app/$c.php";
