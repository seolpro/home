<?php
require_once __DIR__ . '/functions.php';
if (is_logged_in()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pw = (string)post('password');
    if (hash_equals(APP_PASSWORD, $pw)) {
        $_SESSION['traffic_claim_logged_in'] = true;
        redirect('index.php');
    }
    $error = '비밀번호가 올바르지 않습니다.';
}
$title = '로그인';
page_header($title);
?>
<div class="row justify-content-center">
  <div class="col-md-5 col-lg-4">
    <div class="card shadow-sm rounded-4">
      <div class="card-body p-4">
        <h1 class="h4 mb-3">로그인</h1>
        <p class="small-muted">카페24에 올린 뒤 config.php의 APP_PASSWORD로 접속하세요.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="비밀번호" required></div>
          <div class="d-grid"><button class="btn btn-primary">로그인</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php page_footer(); ?>
