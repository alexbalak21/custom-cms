<!doctype html><html data-bs-theme="dark"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="d-flex align-items-center justify-content-center vh-100">
<form method="post" action="<?= e(url('admin/login')) ?>" class="card p-4" style="width:320px">
  <h4 class="mb-3">Custom CMS Admin</h4>
  <?php if ($err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif ?>
  <input type="hidden" name="csrf" value="<?= e(Auth::csrf()) ?>">
  <input class="form-control mb-2" name="username" placeholder="Username" autofocus required>
  <input class="form-control mb-3" type="password" name="password" placeholder="Password" required>
  <button class="btn btn-primary">Sign in</button>
</form></body></html>
