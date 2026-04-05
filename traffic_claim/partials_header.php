<?php require_once __DIR__ . '/config.php'; ?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f7fb}.navbar-brand{font-weight:700}.small-muted{color:#6c757d;font-size:.92rem}.thumb{width:88px;height:88px;object-fit:cover;border-radius:12px;border:1px solid #dee2e6;background:#fff}.card{border:none}.sticky-actions{position:sticky;top:12px;z-index:10}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}.preline{white-space:pre-line}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom mb-4">
  <div class="container">
    <a class="navbar-brand" href="<?= e(app_url('index.php')) ?>"><?= e(APP_NAME) ?></a>
    <div class="ms-auto d-flex gap-2">
      <?php if (is_logged_in()): ?>
        <a href="<?= e(app_url('case_form.php')) ?>" class="btn btn-primary btn-sm">+ 사건 추가</a>
        <a href="<?= e(app_url('logout.php')) ?>" class="btn btn-outline-secondary btn-sm">로그아웃</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container pb-5">
<?php if (!empty($flash)): ?>
  <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
