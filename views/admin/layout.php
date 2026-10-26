<!doctype html>
<html lang="en" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Custom CMS Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
  <ul class="nav nav-pills">
    <?php $active = $view === 'page' ? 'pages' : $view;
    foreach (['dashboard' => ['Dashboard', 'admin'], 'pages' => ['Pages', 'admin/pages'], 'blocks' => ['Blocks', 'admin/blocks'], 'media' => ['Media', 'admin/media'], 'categories' => ['Categories', 'admin/categories'], 'settings' => ['Settings', 'admin/settings']] as $k => [$l, $u]): ?>
    <li class="nav-item"><a class="nav-link <?= $active === $k ? 'active' : '' ?>" href="<?= e(url($u)) ?>"><?= $l ?></a></li><?php endforeach ?>
  </ul>
  <div id="tools" class="d-flex gap-2 align-items-center ms-auto flex-wrap"></div>
  <form method="post" action="<?= e(url('admin/logout')) ?>" class="m-0 d-flex align-items-center gap-2">
    <span class="small text-muted"><?= e($user['name'] ?? '') ?></span>
    <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>"><button class="btn btn-sm btn-outline-secondary">Logout</button>
  </form>
</nav>
<?php if ($view === '404'): ?>
  <div class="scroll text-center pt-5"><h1 class="display-1">404</h1><p class="lead">This admin page does not exist.</p><a class="btn btn-primary" href="<?= e(url('admin')) ?>">Back to dashboard</a></div>
<?php else: ?>
  <div class="main" id="app"></div>
  <script>const CSRF=<?= json_encode(Auth::csrf()) ?>, BASE=<?= json_encode(rtrim(cfg('base_path', ''), '/')) ?>, PAGE=<?= json_encode(['view' => $view, 'id' => $id]) ?>;</script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= e(url('assets/admin.js')) ?>"></script>
<?php endif ?>
</body></html>
