<?php
require_once __DIR__.'/../config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = (string)($_POST['password'] ?? '');
    if (hash_equals(ADMIN_PASSWORD, $pw)) {
        $_SESSION['yt_admin'] = true;
        header('Location: index.php');
        exit;
    }
    $error = '비밀번호가 올바르지 않습니다.';
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>관리자 로그인</title>
  <link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="login-body">
  <main class="login-card">
    <h1>영상 관리자</h1>
    <p>관리자 비밀번호를 입력하세요.</p>
    <?php if($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
    <form method="post" class="form">
      <label>비밀번호<input type="password" name="password" required autofocus></label>
      <button type="submit">로그인</button>
    </form>
  </main>
</body>
</html>
