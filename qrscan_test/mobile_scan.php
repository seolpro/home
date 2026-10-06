<?php
require __DIR__ . '/config.php';
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>모바일 QR 참석체크 테스트</title>
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f9;color:#172033;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{max-width:560px;margin:auto;padding:18px}.card{background:#fff;border-radius:20px;padding:20px;box-shadow:0 8px 28px rgba(0,0,0,.08)}h1{font-size:26px;margin:0 0 8px}.sub{color:#667085;margin:0 0 18px;line-height:1.55}#reader{width:100%;overflow:hidden;border-radius:16px;background:#111}.result{display:none;margin-top:16px;padding:18px;border-radius:14px;font-size:20px;font-weight:800;line-height:1.45;text-align:center}.result.ok{display:block;background:#d1e7dd;color:#0f5132}.result.warn{display:block;background:#fff3cd;color:#664d03}.result.err{display:block;background:#f8d7da;color:#842029}.status{margin-top:14px;text-align:center;color:#667085;font-size:14px}.btns{display:flex;gap:8px;margin-top:16px}.btn{flex:1;border:0;border-radius:12px;padding:14px;font-weight:800;font-size:16px}.dark{background:#212529;color:#fff}.muted{background:#e9ecef;color:#333}.last{margin-top:14px;padding:12px;border:1px solid #e5e7eb;border-radius:12px;font-size:13px;word-break:break-all;color:#667085}.badge{display:inline-block;padding:4px 9px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:800;margin-bottom:8px}
</style>
</head>
<body>
<main class="wrap">
  <div class="card">
    <div class="badge">노트북/패드/스마트폰 스캔용</div>
    <h1>QR 참석체크</h1>
    <p class="sub">조합원의 휴대폰에 표시된 QR 참석증을 카메라에 비춰주세요. 카메라는 계속 유지되며 인식 후 자동으로 다음 QR을 기다립니다.</p>

    <div id="reader"></div>
    <div id="result" class="result"></div>
    <div id="status" class="status">카메라 시작 준비중...</div>
    <div id="last" class="last" style="display:none"></div>

    <div class="btns">
      <button id="stop" class="btn muted" type="button">카메라 중지</button>
      <button id="start" class="btn muted" type="button">카메라 다시 시작</button>
    </div>
    <div class="btns">
      <button class="btn dark" type="button" onclick="location.href='admin.php'">참석자 명부</button>
    </div>
  </div>
</main>
<script>
const readerId = 'reader';
const resultEl = document.getElementById('result');
const statusEl = document.getElementById('status');
const lastEl = document.getElementById('last');

let scanner = null;
let busy = false;
let running = false;
let lastCode = '';
let lastAt = 0;
let resultTimer = null;

// 동일 QR을 카메라 앞에 계속 두는 경우 재등록 요청을 막는 시간
const SAME_CODE_DELAY = 5000;
// 결과 표시 후 다음 QR을 받을 때까지의 짧은 잠금 시간
const NEXT_SCAN_DELAY = 1500;

function showResult(type, html) {
  if (resultTimer) clearTimeout(resultTimer);
  resultEl.className = 'result ' + type;
  resultEl.innerHTML = html;
}

function clearResult() {
  resultEl.className = 'result';
  resultEl.innerHTML = '';
}

function scheduleResultClear() {
  if (resultTimer) clearTimeout(resultTimer);
  resultTimer = setTimeout(() => {
    clearResult();
    if (running && !busy) {
      statusEl.textContent = 'QR 스캔 대기중 · 다음 조합원 QR을 비춰주세요.';
    }
  }, 2500);
}

async function registerCode(code) {
  const now = Date.now();

  if (busy) return;

  // 같은 QR이 여러 프레임에서 계속 잡혀도 일정 시간 동안 무시
  if (code === lastCode && now - lastAt < SAME_CODE_DELAY) return;

  busy = true;
  lastCode = code;
  lastAt = now;

  statusEl.textContent = 'QR 확인 및 참석 등록중...';
  lastEl.style.display = 'block';
  lastEl.textContent = '최근 인식값: ' + code;

  try {
    const body = new URLSearchParams();
    body.set('code', code);

    const res = await fetch('api_checkin.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
      body: body.toString(),
      credentials: 'same-origin',
      cache: 'no-store'
    });

    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error('서버 응답을 JSON으로 읽을 수 없습니다. HTTP ' + res.status);
    }

    if (data.ok) {
      showResult('ok', data.message || '✓ 참석 등록 완료');
      statusEl.textContent = '등록 완료 · 잠시 후 자동으로 다음 QR을 인식합니다.';
    } else {
      const msg = data.message || '등록되지 않았습니다.';
      showResult(msg.includes('이미 참석') ? 'warn' : 'err', msg);
      statusEl.textContent = '처리 결과 확인 · 잠시 후 자동으로 다음 QR을 인식합니다.';
    }

  } catch (e) {
    showResult('err', '서버 통신 오류가 발생했습니다.<br><small>' +
      String(e.message || e) + '</small>');
    statusEl.textContent = '카메라는 유지됩니다. 네트워크와 서버 상태를 확인하세요.';
  } finally {
    // 카메라는 절대 stop 하지 않고 인식 처리만 잠깐 잠근다.
    setTimeout(() => {
      busy = false;
      if (running) {
        statusEl.textContent = 'QR 스캔 대기중 · 다음 조합원 QR을 비춰주세요.';
      }
      scheduleResultClear();
    }, NEXT_SCAN_DELAY);
  }
}

async function startCamera() {
  if (running || scanner) return;

  clearResult();
  statusEl.textContent = '카메라를 시작하는 중...';

  try {
    scanner = new Html5Qrcode(readerId);

    const config = {
      fps: 10,
      qrbox: function(viewfinderWidth, viewfinderHeight) {
        const size = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.72);
        return {width:size, height:size};
      },
      aspectRatio: 1.0
    };

    await scanner.start(
      { facingMode: 'environment' },
      config,
      decodedText => registerCode(decodedText),
      () => {}
    );

    running = true;
    statusEl.textContent = 'QR 스캔 대기중 · 조합원 QR을 사각형 안에 맞춰주세요.';

  } catch (e) {
    running = false;
    scanner = null;
    showResult('err',
      '카메라를 열 수 없습니다.<br><small>카메라 권한과 HTTPS 접속을 확인하세요.</small>');
    statusEl.textContent = String(e.message || e);
  }
}

async function stopCamera() {
  if (!scanner) return;

  try {
    if (running) await scanner.stop();
  } catch (e) {}

  try {
    await scanner.clear();
  } catch (e) {}

  running = false;
  scanner = null;
  busy = false;
}

document.getElementById('stop').addEventListener('click', async () => {
  await stopCamera();
  statusEl.textContent = '카메라가 중지되었습니다.';
});

document.getElementById('start').addEventListener('click', async () => {
  await stopCamera();
  lastCode = '';
  lastAt = 0;
  await startCamera();
});

window.addEventListener('pagehide', () => {
  stopCamera();
});

// 페이지 최초 진입 시 한 번만 카메라 시작.
// 이후 QR을 여러 번 읽어도 카메라 스트림을 종료/재시작하지 않는다.
startCamera();
</script>
</body>
</html>
