<?php
require __DIR__ . '/../lib.php';
Auth::start();
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Auth::user()) {
    $err = !hash_equals(Auth::csrf(), $_POST['csrf'] ?? '') ? 'Session expired, retry' : (Auth::login($_POST['username'] ?? '', $_POST['password'] ?? '') ?? '');
    if (!$err) { header('Location: index.php'); exit; }
}
$boot = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
if (!Auth::user()): ?>
<!doctype html><html data-bs-theme="dark"><head><meta charset="utf-8"><title>Login</title><?= $boot ?></head>
<body class="d-flex align-items-center justify-content-center vh-100">
<form method="post" class="card p-4" style="width:320px">
  <h4 class="mb-3">Custom CMS Admin</h4>
  <?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif ?>
  <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
  <input class="form-control mb-2" name="username" placeholder="Username" autofocus required>
  <input class="form-control mb-3" type="password" name="password" placeholder="Password" required>
  <button class="btn btn-primary">Sign in</button>
</form></body></html>
<?php exit; endif; ?>
<!doctype html>
<html lang="en" data-bs-theme="dark"><head><meta charset="utf-8"><title>Custom CMS Admin</title><?= $boot ?>
<style>
html,body{height:100%} body{display:flex;flex-direction:column}
.main{flex:1;min-height:0;display:flex}
.split{flex:1;min-height:0;display:flex;flex-direction:column}.split.rev{flex-direction:column-reverse}
.pane{min-height:0;overflow:auto}
iframe{width:100%;height:100%;border:0;background:#fff}
.preview{flex:1 1 50%}.editor{flex:1 1 50%;padding:.75rem}
.editor textarea,.mono{font-family:ui-monospace,monospace;font-size:.85rem}
.editor textarea{height:100%;resize:none}
textarea.bad{border-color:#dc3545!important}
.side{width:420px;flex:none;overflow:auto;padding:.75rem;border-right:1px solid var(--bs-border-color)}
.pv{flex:1;min-width:0}
.scroll{flex:1;overflow:auto;padding:1rem}
.thumb{height:130px;object-fit:cover;width:100%;background:#222}
</style></head>
<body>
<nav class="navbar bg-body-tertiary px-3 py-2 gap-3 flex-wrap">
  <span class="navbar-brand mb-0 h1">Custom CMS</span>
  <ul class="nav nav-pills" id="nav">
    <?php foreach (['dashboard'=>'Dashboard','pages'=>'Pages','blocks'=>'Blocks','media'=>'Media','categories'=>'Categories','settings'=>'Settings'] as $k=>$l): ?>
    <li class="nav-item"><a class="nav-link" data-v="<?= $k ?>" href="#<?= $k ?>"><?= $l ?></a></li><?php endforeach ?>
  </ul>
  <div id="tools" class="d-flex gap-2 align-items-center ms-auto flex-wrap"></div>
  <a class="btn btn-sm btn-outline-secondary" href="?logout=1">Logout</a>
</nav>
<div class="main" id="app"></div>
<script>const CSRF=<?= json_encode(Auth::csrf()) ?>, BASE=<?= json_encode(rtrim(cfg('base_path',''),'/')) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="app.js"></script>
</body></html>
