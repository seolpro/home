<?php
// /www/coupon_reminder/test_alimtalk.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ppurio_coupon_lib.php';

$resultMsg = '';
$rawRes    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to   = isset($_POST['to']) ? $_POST['to'] : '';
    $var1 = trim($_POST['var1'] ?? '');
    $var2 = trim($_POST['var2'] ?? '');
    $var3 = trim($_POST['var3'] ?? '');
    $var4 = trim($_POST['var4'] ?? '');

    $toDigits = preg_replace('/[^0-9]/', '', $to);

    if ($toDigits === '' || strlen($toDigits) < 10) {
        $resultMsg = '❌ 수신번호를 올바르게 입력해 주세요. (예: 01012345678)';
    } else {
        // 템플릿 변수 배열 (템플릿에서 [*1*] ~ [*4*] 에 대응)
        $variables = [$var1, $var2, $var3, $var4];

        // msg 는 디버그용 텍스트
        $msg = "[테스트 알림톡]\n" .
               "var1={$var1}, var2={$var2}, var3={$var3}, var4={$var4}";

        $res = coupon_sendAlimtalk($toDigits, $msg, $variables);

        if ($res['ok']) {
            $resultMsg = '✅ 알림톡 발송 요청이 정상 처리되었습니다. (Ppurio 응답 OK)';
        } else {
            $resultMsg = '❌ 발송 요청 중 오류가 발생했습니다. HTTP 코드 및 응답을 확인해 주세요.';
        }

        $rawRes = isset($res['raw']) ? $res['raw'] : json_encode($res, JSON_UNESCAPED_UNICODE);
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>📡 뿌리오 알림톡 발송 테스트 (쿠폰용)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">
  <style>
    body {
      background:#f5f7fb;
      font-family: system-ui, -apple-system, 'Noto Sans KR', sans-serif;
    }
    .card {
      border-radius: 1.2rem;
      box-shadow: 0 6px 16px rgba(15,23,42,0.08);
      border: none;
    }
    .page-header {
      text-align:center;
      margin: 24px 0 16px;
    }
    .page-header h2 {
      font-weight: 700;
    }
    .page-header p {
      color:#6c757d;
      font-size:0.95rem;
    }
    .badge-env {
      font-size:0.75rem;
    }
    pre {
      font-size:0.8rem;
      background:#0f172a;
      color:#e5e7eb;
      border-radius:0.75rem;
      padding:12px;
      max-height:260px;
      overflow:auto;
    }
  </style>
</head>
<body>
<div class="container my-4">
  <div class="page-header">
    <h2>📡 뿌리오 알림톡 발송 테스트 (쿠폰용)</h2>
    <p>
      coupon_reminder에서 사용하는 Ppurio 설정으로<br>
      실제 알림톡 발송이 정상 동작하는지 점검하는 페이지입니다.
    </p>
    <div class="mt-1">
      <span class="badge text-bg-light badge-env">
        계정: <?= htmlspecialchars(PPURIO_ACCOUNT) ?>
      </span>
      <span class="badge text-bg-light badge-env">
        프로필: <?= htmlspecialchars(PPURIO_SENDER_PROFILE) ?>
      </span>
      <span class="badge text-bg-light badge-env">
        템플릿코드: <?= htmlspecialchars(PPURIO_TEMPLATE_CODE) ?>
      </span>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title mb-3">🧪 테스트 발송 폼</h5>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">수신번호 (휴대폰)</label>
          <input type="tel" name="to" class="form-control"
                 placeholder="예) 01012345678"
                 value="<?= isset($_POST['to']) ? htmlspecialchars($_POST['to']) : '' ?>"
                 required>
          <div class="form-text">
            실제 알림톡을 받아볼 본인 휴대폰 번호를 입력하세요. 숫자만 입력해도 됩니다.
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">템플릿 변수 1 ([*1*])</label>
            <input type="text" name="var1" class="form-control"
                   value="<?= isset($_POST['var1']) ? htmlspecialchars($_POST['var1']) : '' ?>"
                   placeholder="예) 테스트쿠폰">
          </div>
          <div class="col-md-6">
            <label class="form-label">템플릿 변수 2 ([*2*])</label>
            <input type="text" name="var2" class="form-control"
                   value="<?= isset($_POST['var2']) ? htmlspecialchars($_POST['var2']) : '' ?>"
                   placeholder="예) 2025-12-31">
          </div>
          <div class="col-md-6">
            <label class="form-label">템플릿 변수 3 ([*3*])</label>
            <input type="text" name="var3" class="form-control"
                   value="<?= isset($_POST['var3']) ? htmlspecialchars($_POST['var3']) : '' ?>"
                   placeholder="예) D-10">
          </div>
          <div class="col-md-6">
            <label class="form-label">템플릿 변수 4 ([*4*])</label>
            <input type="text" name="var4" class="form-control"
                   value="<?= isset($_POST['var4']) ? htmlspecialchars($_POST['var4']) : '' ?>"
                   placeholder="예) 아주대의료원신협">
          </div>
        </div>

        <div class="mt-4 text-end">
          <button type="submit" class="btn btn-primary">
            🚀 테스트 알림톡 발송
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($resultMsg): ?>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title mb-2">📣 발송 결과</h5>
        <p><?= htmlspecialchars($resultMsg) ?></p>
        <?php if ($rawRes): ?>
          <h6 class="mt-3 mb-2">Ppurio 원본 응답(JSON)</h6>
          <pre><?= htmlspecialchars($rawRes) ?></pre>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
