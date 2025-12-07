<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ppurio_coupon_lib.php';// 알림톡 전송에 사용

// 이미 로그인 상태면 바로 index로
if (!empty($_SESSION['coupon_owner_tel'])) {
    header('Location: index.php');
    exit;
}

$step       = isset($_POST['step']) ? $_POST['step'] : 'input';
$telInput   = isset($_POST['tel']) ? $_POST['tel'] : '';
$telDigits  = preg_replace('/[^0-9]/', '', $telInput);
$message    = '';
$codeInput  = isset($_POST['code']) ? trim($_POST['code'] ?? '') : '';

if ($step === 'send') {
    // 1단계: 인증번호 보내기
    if (strlen($telDigits) < 10) {
        $message = '📵 휴대폰 번호를 올바르게 입력해 주세요.';
        $step = 'input';
    } else {
        // 6자리 인증번호 생성
        $code = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // 세션에 저장 (5분짜리)
        $_SESSION['coupon_login_tel']  = $telDigits;
        $_SESSION['coupon_login_code'] = $code;
        $_SESSION['coupon_login_exp']  = time() + 300;

    // 🔸 알림톡 발송은 잠시 막고, 화면에 인증번호를 보여주는 테스트 모드
    $message = "✅ [테스트 모드] 인증번호는 {$code} 입니다. 화면에 보이는 번호를 그대로 입력해 주세요.";
    $step = 'verify';

        // 알림톡 or SMS 발송
        $msg = "[쿠폰 사용만료 알리미 로그인]\n"
             . "인증번호: {$code}\n"
             . "5분 이내에 입력해 주세요.";

        // 템플릿에 [*1*] 자리에 code를 넣는다고 가정
        $res = coupon_sendAlimtalk($telDigits, $msg, [$code]);

        if ($res['ok']) {
            $message = '✅ 인증번호가 발송되었습니다. 카카오톡 알림톡을 확인해 주세요.';
            $step = 'verify';
        } else {
            $message = '❌ 인증번호 발송 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.';
            $step = 'input';
        }
    }
} elseif ($step === 'verify') {
    // 2단계: 인증번호 검증
    $savedTel  = $_SESSION['coupon_login_tel']  ?? '';
    $savedCode = $_SESSION['coupon_login_code'] ?? '';
    $savedExp  = $_SESSION['coupon_login_exp']  ?? 0;

    if (!$savedTel || !$savedCode || !$savedExp) {
        $message = '❌ 인증 세션이 만료되었습니다. 다시 시도해 주세요.';
        $step = 'input';
    } elseif (time() > $savedExp) {
        $message = '⏰ 인증번호 유효시간(5분)이 지났습니다. 다시 받으세요.';
        $step = 'input';
    } elseif ($codeInput === '' || $codeInput !== $savedCode) {
        $message = '❌ 인증번호가 올바르지 않습니다.';
        $step = 'verify';
    } else {
        // 로그인 성공
        $_SESSION['coupon_owner_tel'] = $savedTel;

        // 인증용 세션은 제거
        unset($_SESSION['coupon_login_tel'], $_SESSION['coupon_login_code'], $_SESSION['coupon_login_exp']);

        header('Location: index.php');
        exit;
    }
} else {
    $step = 'input';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>🎫 쿠폰 유효기간 알리미 - 로그인</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">
  <style>
    body {
      background:#f5f7fb;
      font-family: system-ui, -apple-system, 'Noto Sans KR', sans-serif;
    }
    .login-card {
      max-width: 420px;
      margin: 60px auto;
      border-radius: 1.5rem;
      box-shadow: 0 10px 30px rgba(15,23,42,0.18);
      border:none;
    }
    .brand {
      text-align:center;
      margin-bottom: 16px;
    }
    .brand h2 {
      font-weight:700;
      margin-bottom:4px;
    }
    .brand p {
      color:#6c757d;
      font-size:0.9rem;
      margin-bottom:0;
    }
  </style>
</head>
<body>
<div class="container">
  <div class="card login-card">
    <div class="card-body p-4">
      <div class="brand">
        <h2>🎫 쿠폰 유효기간 알리미</h2>
        <p>휴대폰 번호로 로그인하여 내 쿠폰만 관리합니다.</p>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-info py-2"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <?php if ($step === 'input'): ?>
        <!-- 1단계: 휴대폰 번호 입력 -->
        <form method="post">
          <input type="hidden" name="step" value="send">
          <div class="mb-3">
            <label class="form-label">휴대폰 번호</label>
            <input type="tel" name="tel" class="form-control"
                   placeholder="예: 01012345678"
                   value="<?= htmlspecialchars($telInput) ?>"
                   required>
            <div class="form-text">
              숫자만 입력해 주세요. 해당 번호로 인증번호를 발송합니다.
            </div>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary">
              📩 인증번호 받기
            </button>
          </div>
        </form>

      <?php elseif ($step === 'verify'): ?>
        <!-- 2단계: 인증번호 입력 -->
        <form method="post">
          <input type="hidden" name="step" value="verify">
          <div class="mb-3">
            <label class="form-label">인증번호 (6자리)</label>
            <input type="text" name="code" class="form-control"
                   placeholder="예: 123456"
                   value="<?= htmlspecialchars($codeInput) ?>"
                   required>
            <div class="form-text">
              문자/알림톡으로 받은 인증번호를 입력해 주세요. (5분 유효)
            </div>
          </div>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success">
              ✅ 로그인
            </button>
            <a href="login.php" class="btn btn-outline-secondary btn-sm">
              ↩ 처음부터 다시
            </a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
