<?php $v = ($_GET['v'] ?? 'pages') === 'blocks' ? 'blocks' : 'pages'; ?>
<!doctype html>
<html lang="en" data-bs-theme="dark"><head><meta charset="utf-8">
<title>Enwires Builder</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
html,body{height:100%} body{display:flex;flex-direction:column}
.main{flex:1;min-height:0;display:flex}
.split{flex:1;min-height:0;display:flex;flex-direction:column}
.split.rev{flex-direction:column-reverse}
.pane{min-height:0;overflow:auto}
iframe{width:100%;height:100%;border:0;background:#fff}
.preview{flex:1 1 50%} .editor{flex:1 1 50%;padding:.75rem}
.editor textarea{font-family:ui-monospace,monospace;font-size:.85rem;height:100%;resize:none}
.editor textarea.bad{border-color:#dc3545}
.side{width:400px;flex:none;overflow:auto;padding:.75rem;border-right:1px solid var(--bs-border-color)}
.pv{flex:1;min-width:0}
</style></head>
<body>
<nav class="navbar bg-body-tertiary px-3 py-2 gap-3 flex-wrap">
  <span class="navbar-brand mb-0 h1">Enwires Builder</span>
  <ul class="nav nav-pills">
    <li class="nav-item"><a class="nav-link <?= $v=='pages'?'active':'' ?>" href="?v=pages">Page Builder</a></li>
    <li class="nav-item"><a class="nav-link <?= $v=='blocks'?'active':'' ?>" href="?v=blocks">Block Builder</a></li>
  </ul>
  <div id="tools" class="d-flex gap-2 align-items-center ms-auto flex-wrap"></div>
</nav>
<div class="main" id="app"></div>
<script>const VIEW = <?= json_encode($v) ?>;</script>
<script src="app.js"></script>
</body></html>