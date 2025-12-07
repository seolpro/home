<?php
// /www/coupon_reminder/test_login_alimtalk.php

// 🔧 개발용: 에러 화면에 바로 표시
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ppurio_coupon_lib.php'; // coupon_sendAlimtalk 사용

$resultMsg = '';
$rawRes    = '';
$toInput   = '';
$codeInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toInput   = $_POST['to']   ?? '';
    $codeInput = $_POST['code'] ?? '';

    // 숫자만 추출
    $telDigits = preg_replace('/[^0-9]/', '', $toInput);

    if (strlen($telDigits) < 10) {
        $resultMsg = '❌ 수신 휴대폰 번호를 올바르게 입력해 주세요. (예: 01012345678)';
    } else {
        // 인증번호: 입력이 없으면 6자리 랜덤 생성
        $code = trim($codeInput);
        if ($code === '') {
            $code = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        // 디버그용 메시지 (실제 템플릿 내용과는 무관)
        $msg = "[로그인 인증번호 테스트]\n"
             . "인증번호: {$code}\n"
             . "(이 알림톡은 테스트용으로 발송되었습니다.)";

        // 로그인용 템플릿 코드 선택
        // config.php 에 예를 들면:
        // define('PPURIO_TEMPLATE_CODE_LOGIN', 'ppur_로그인템플릿코드');
        $templateCode = null;
        if (defined('PPURIO_TEMPLATE_CODE_LOGIN')) {
            $templateCode = PPURIO_TEMPLATE_CODE_LOGIN;
        } elseif (defined('PPURIO_TEMPLATE_CODE_COUPON')) {
            // 로그인용이 따로 없으면, 쿠폰 템플릿으로라도 테스트
            $templateCode = PPURIO_TEMPLATE_CODE_COUPON;
        }

        // [*1*] = 인증번호 로 가정하고 var1 에 code 전달
        $res = coupon_sendAlimtalk(
            $telDigits,
            $msg,
            [$code],       // var1 -> [*1*]
            $templateCode  // 로그인 템플릿 코드
        );

        if ($res['ok']) {
            $resultMsg = '✅ 로그인 인증번호 템플릿으로 알림톡 발송 요청이 정상 처리되었습니다.';
        } else {
            $resultMsg = '❌ 발송 요청 중 오류가 발생했습니다. 아래 원본 응답을 확인해 주세요.';
        }

        $rawRes = isset($res['raw']) ? $res['raw'] : json_encode($res, JSON_UNESCAPED_UNICODE);
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>📡 로그인 인증번호 알림톡 테스트 발송</title>
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
    <h2>📡 로그인 인증번호 알림톡 테스트</h2>
    <p>
      로그인용 인증번호 템플릿으로 실제 알림톡 발송이 되는지 확인하는 페이지입니다.
    </p>
    <div class="mt-1">
      <span class="badge text-bg-light badge-env">
        계정: <?= htmlspecialchars(PPURIO_ACCOUNT) ?>
      </span>
      <?php if (defined('PPURIO_SENDER_PROFILE')): ?>
      <span class="badge text-bg-light badge-env">
        프로필: <?= htmlspecialchars(PPURIO_SENDER_PROFILE) ?>
      </span>
      <?php endif; ?>
      <?php if (defined('PPURIO_TEMPLATE_CODE_LOGIN')): ?>
      <span class="badge text-bg-light badge-env">
        로그인 템플릿: <?= htmlspecialchars(PPURIO_TEMPLATE_CODE_LOGIN) ?>
      </span>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <h5 class="card-title mb-3">🧪 테스트 발송 폼</h5>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">수신 휴대폰 번호</label>
          <input type="tel" name="to" class="form-control"
                 placeholder="예: 01012345678"
                 value="<?= htmlspecialchars($toInput) ?>"
                 required>
          <div class="form-text">
            실제로 인증번호를 받아볼 번호를 입력하세요. 숫자만 입력해도 됩니다.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">인증번호 (선택 입력)</label>
          <input type="text" name="code" class="form-control"
                 placeholder="비워두면 6자리 랜덤 생성"
                 value="<?= htmlspecialchars($codeInput) ?>">
          <div class="form-text">
            특정 번호로 테스트하고 싶으면 직접 입력하고, 아니면 비워두면 6자리 랜덤 번호가 생성됩니다.
          </div>
        </div>

        <div class="mt-4 text-end">
          <button type="submit" class="btn btn-primary">
            🚀 로그인 인증번호 알림톡 발송
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
