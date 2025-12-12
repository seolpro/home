<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = getDB();

// 최근 쿠폰 목록 (예: 최근 50개)
/* $stmt = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC LIMIT 50");
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC); */
// ✅ 쿠폰 목록 (미사용 우선 + 유효기간 임박순)
// ✅ 쿠폰 목록: 미사용 먼저 + 유효기간 임박순 + (동일 날짜면 최근등록 우선)
$stmt = $pdo->query("
  SELECT *
  FROM coupons
  ORDER BY
    used_flag ASC,
    expire_date ASC,
    created_at DESC
  LIMIT 50
");
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>🎫 쿠폰관리 어플리케이션</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">

<!-- Bootstrap 5 JS (bundle: modal 포함) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <style>
    body {
      background: #f5f7fb;
      font-family: system-ui, -apple-system, 'Noto Sans KR', sans-serif;
    }
    .page-header {
      text-align:center;
      margin: 20px 0;
    }
    .page-header h2 {
      font-weight: 700;
    }
    .page-header p {
      color:#6c757d;
      font-size: 0.95rem;
    }
    .card {
      border-radius: 1.25rem;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
      border: none;
    }
    .badge-status {
      font-size: 0.8rem;
    }
    .coupon-img-thumb {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 0.75rem;
      border: 1px solid #e9ecef;
    }
    .table thead th {
      font-size: 0.85rem;
      color:#6c757d;
    }
    .table tbody td {
      vertical-align: middle;
      font-size:0.9rem;
    }
    .btn-pill {
      border-radius: 999px;
    }
  </style>
</head>
<body>
<div class="container my-4">
  <div class="page-header">
    <h2>🎫 쿠폰 유효기간 알리미</h2>
    <p>받은 모바일·실물 쿠폰을 등록해 두면, 유효기간 10일·5일·2일 전에 알림톡으로 알려드립니다.</p>
  </div>

  <!-- 등록 폼 -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title mb-3">📝 새 쿠폰 등록</h5>
      <form action="save_coupon.php" method="post" enctype="multipart/form-data" id="couponForm">
        <div class="row g-3">
         <div class="col-md-6">
  <label class="form-label">쿠폰 이미지</label>

  <!-- 실제 업로드에 사용되는 input (숨김) -->
  <input type="file"
         name="image"
         id="couponImage"
         accept="image/*"
         class="form-control d-none">

  <div class="btn-group mt-1" role="group">
    <button type="button"
            class="btn btn-outline-secondary btn-sm"
            onclick="selectCouponImage('camera')">
      📷 사진 촬영
    </button>
    <button type="button"
            class="btn btn-outline-secondary btn-sm"
            onclick="selectCouponImage('file')">
      🖼 이미지 선택
    </button>
  </div>

  <div class="form-text mt-1">
    쿠폰을 촬영하거나, 갤러리에서 이미지를 선택해 주세요.
    선택 후 필요하면 <b>이미지에서 정보 인식하기</b> 버튼을 눌러 주세요.
  </div>

  <button type="button" class="btn btn-outline-primary btn-sm mt-2"
          onclick="runCouponOcr()">
    🔍 이미지에서 정보 인식하기
  </button>

  <div id="ocrStatus" class="small text-muted mt-1"></div>
</div>

          <div class="col-md-6">
            <label class="form-label">🎁쿠폰명 <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="예) 스타벅스 아메리카노 쿠폰" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">☎만료안내받을 연락처 <span class="text-danger">*</span></label>
            <input
  type="tel"
  name="receiver_tel"
  class="form-control"
  value="01071186639"
  readonly
  required
>
          </div>
          
          <div class="col-md-6">
            <label class="form-label">🔢쿠폰 번호/코드</label>
            <input type="text" name="coupon_code" class="form-control" placeholder="예) ABCD-EFGH-1234">
          </div>
          <div class="col-md-6">
            <label class="form-label">🎫바코드 번호(선택)</label>
            <input type="text" name="barcode" class="form-control" placeholder="필요시입력">
          </div>
          <div class="col-md-6">
            <label class="form-label">📆유효기간(마지막 사용일) <span class="text-danger">*</span></label>
            <input type="date" name="expire_date" class="form-control" required>
          </div>

        <div class="mt-3 text-end">
          <button type="submit" class="btn btn-primary btn-pill">
            💾 쿠폰 등록
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- 쿠폰 목록 -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title mb-3">📂 등록된 쿠폰 목록(*만료일 빠른순)</h5>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
          <tr>
            <th>이미지</th>
            <th>쿠폰 정보</th>
            <th>유효기간</th>
            <th>상태</th>
            <th>사용등록</th>
            <th>삭제</th> <!-- ✅ 여기에 추가 -->
          </tr>
          </thead>
          <tbody>
          <?php if (empty($coupons)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">등록된 쿠폰이 없습니다.</td></tr>
          <?php else: ?>
            <?php foreach ($coupons as $c):
              $today = new DateTime('today');
              $exp   = new DateTime($c['expire_date']);
              $diff  = (int)$today->diff($exp)->format('%r%a'); // 음수까지 계산
              $statusText = '';
              $statusClass = 'bg-secondary';

              if ($c['used_flag']) {
                  $statusText = '사용완료';
                  $statusClass = 'bg-success';
              } else {
                  if ($diff < 0) {
                      $statusText = '기간만료';
                      $statusClass = 'bg-dark';
                  } elseif ($diff === 0) {
                      $statusText = 'D-day';
                      $statusClass = 'bg-danger';
                  } elseif ($diff > 0) {
                      $statusText = 'D-' . $diff;
                      $statusClass = $diff <= 5 ? 'bg-danger' : ($diff <= 10 ? 'bg-warning text-dark' : 'bg-info');
                  }
              }
            ?>
                          <tr>
                <td>
  <?php
    // 사용완료 여부 플래그 (모달 오버레이용)
    $isUsed = !empty($c['used_flag']);
  ?>

  <?php if (!empty($c['image_path']) && file_exists($c['image_path'])): ?>
    <?php
      // 파일 시스템 경로 → 웹에서 보이는 상대경로로 변환 (예: uploads/xxx.jpg)
      $imgUrl = str_replace(__DIR__ . '/', '', $c['image_path']);
    ?>
    <img
      src="<?= htmlspecialchars($imgUrl) ?>"
      class="coupon-img-thumb"
      alt="쿠폰 이미지"
      style="cursor:pointer;"
      data-full="<?= htmlspecialchars($imgUrl) ?>"
      data-used="<?= $isUsed ? '1' : '0' ?>"
      onclick="openImageModal('<?= htmlspecialchars($imgUrl) ?>', <?= $isUsed ? 'true' : 'false' ?>)"
    >
  <?php else: ?>
    <span class="text-muted small">이미지 없음</span>
  <?php endif; ?>
                </td>

                  <td>
                  <div class="fw-semibold"><?= htmlspecialchars($c['title']) ?></div>
                  <div class="text-muted small">
                    📞 <?= htmlspecialchars($c['receiver_tel']) ?><br>
                    <?php if ($c['barcode']): ?>바코드: <?= htmlspecialchars($c['barcode']) ?><br><?php endif; ?>
                    <?php if ($c['coupon_code']): ?>코드: <?= htmlspecialchars($c['coupon_code']) ?><?php endif; ?>
                  </div>
                </td>
                <td>
                  <?= htmlspecialchars($c['expire_date']) ?>
                </td>
                <td>
                  <span class="badge badge-status <?= $statusClass ?>"><?= $statusText ?></span>
                </td>
                <td>
                  <?php if ($c['used_flag']): ?>
                    <span class="text-success small">이미 사용등록</span>
                  <?php else: ?>
                    <form action="use_coupon.php" method="post" onsubmit="return confirm('✅이 쿠폰을 사용완료로 등록할까요? 알림톡 발송 대상에서 제외됩니다.');">
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <button type="submit" class="btn btn-outline-success btn-sm btn-pill">
                        ✅ 사용등록
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
                <td>
  <button
    type="button"
    class="btn btn-outline-danger btn-sm"
    onclick="deleteCoupon(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['title'], ENT_QUOTES) ?>', this)">
    삭제
  </button>
</td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 🖼 쿠폰 이미지 확대 모달 -->
<!-- 🖼 쿠폰 이미지 확대 모달 -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-body p-0 position-relative">
        <!-- 닫기 버튼 (우측 상단) -->
        <button type="button"
                class="btn-close position-absolute top-0 end-0 m-2"
                data-bs-dismiss="modal"
                aria-label="Close"></button>

                <!-- ✅ 사용완료 오버레이 -->
        <div id="couponUsedOverlay"
             class="position-absolute top-50 start-50 translate-middle text-center px-8 py-2"
             style="display:none;
                    background:rgba(0,0,0,0.55);
                    color:#fff;
                    font-size:1.2rem;
                    font-weight:700;
                    border-radius:999px;
                    box-shadow:0 0 12px rgba(0,0,0,0.6);">
          ✅사용완료된 쿠폰입니다.
        </div>

        <img id="imageModalImg" src="" alt="쿠폰 원본 이미지" class="img-fluid w-100">
      </div>
    </div>
  </div>
</div>

<!-- ✅ 로딩 시 자동 표시 모달 -->
<div class="modal fade" id="noticeModal" tabindex="-1" aria-labelledby="noticeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="noticeModalLabel">📢 쿠폰사용 안내</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
      </div>

      <div class="modal-body">
        <p class="mb-2">📍유효기간이 먼저 도래하는 쿠폰부터 사용해 주세요</p>
        <ul class="mb-0">
          <li>유효기간 또는 상태(잔여일) 확인</li>
        </ul>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">확인했어요</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('noticeModal');
  if (!el) return;

  const modal = new bootstrap.Modal(el, {
    backdrop: 'static', // 바깥 클릭으로 닫히지 않게
    keyboard: false     // ESC로 닫히지 않게
  });

  modal.show();
});
</script>


<script>
function deleteCoupon(id, title, btnEl) {
  if (!confirm('정말로 이 쿠폰을 삭제하시겠습니까?\n\n쿠폰명: ' + (title || ''))) {
    return;
  }

  // 버튼 비활성화 (중복 클릭 방지)
  if (btnEl) {
    btnEl.disabled = true;
    btnEl.innerText = '삭제 중...';
  }

  fetch('delete_coupon.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
    },
    body: 'id=' + encodeURIComponent(id)
  })
  .then(res => res.json())
  .then(data => {
    if (data.ok) {
      // ✅ 삭제 성공: 해당 행 DOM에서 제거
      if (btnEl) {
        const tr = btnEl.closest('tr');
        if (tr) tr.remove();
      }
    } else {
      alert(data.msg || '삭제 중 오류가 발생했습니다.');
      if (btnEl) {
        btnEl.disabled = false;
        btnEl.innerText = '삭제';
      }
    }
  })
  .catch(err => {
    console.error(err);
    alert('삭제 요청 중 오류가 발생했습니다.');
    if (btnEl) {
      btnEl.disabled = false;
      btnEl.innerText = '삭제';
    }
  });
}
</script>

<script>
function openImageModal(src, isUsed = false) {
  const img      = document.getElementById('imageModalImg');
  const modalEl  = document.getElementById('imageModal');
  const overlay  = document.getElementById('couponUsedOverlay');

  if (!img || !modalEl) return;

  img.src = src;

  // ✅ 사용완료 오버레이 표시/숨김
  if (overlay) {
    overlay.style.display = isUsed ? 'block' : 'none';
  }

  const modal = new bootstrap.Modal(modalEl);
  modal.show();
}
</script>


<script>
function selectCouponImage(mode) {
  const input = document.getElementById('couponImage');
  const statusEl = document.getElementById('ocrStatus');

  if (!input) return;

  // 모드에 따라 capture 속성 on/off
  if (mode === 'camera') {
    input.setAttribute('capture', 'environment'); // 카메라 우선
  } else {
    input.removeAttribute('capture');             // 일반 파일 선택(갤러리)
  }

  // 이전 상태 문구 정리
  if (statusEl) {
    statusEl.textContent = '이미지를 선택해 주세요.';
  }

  // 파일 선택창 열기
  input.click();
}
</script>

<script>
async function runCouponOcr() {
  const fileInput = document.getElementById('couponImage');
  const statusEl  = document.getElementById('ocrStatus');

  if (!fileInput.files || fileInput.files.length === 0) {
    alert('먼저 쿠폰 이미지를 선택해 주세요.');
    fileInput.click();
    return;
  }

  const file = fileInput.files[0];

  statusEl.textContent = '📡 문자 인식 중입니다... (이미지 크기에 따라 수 초 걸릴 수 있어요)';
  
  try {
    const result = await Tesseract.recognize(
      file,
      'kor+eng', // 한글 + 영어 인식
      {
        logger: m => {
          if (m.status === 'recognizing text') {
            statusEl.textContent = `📡 문자 인식 중... ${Math.round(m.progress * 100)}%`;
          }
        }
      }
    );

    statusEl.textContent = '✅ 인식 완료! 필요한 정보를 자동으로 채웁니다.';

    const text = result.data.text || '';
    fillCouponFieldsFromText(text);
  } catch (e) {
    console.error(e);
    statusEl.textContent = '❌ 인식 중 오류가 발생했습니다. (이미지를 다시 촬영해 보세요)';
    alert('문자 인식 중 오류가 발생했습니다.');
  }
}

/**
 * 인식된 텍스트에서
 *  - 쿠폰번호(코드)
 *  - 유효기간
 *  - 제목(대략)
 * 을 추출해서 폼에 채워넣는 함수
 */
function fillCouponFieldsFromText(text) {
  // 1) 줄 단위로 정리
  const lines = text.split(/\r?\n/)
    .map(l => l.replace(/\s+$/g, '').trim()) // 오른쪽 공백 제거
    .filter(l => l.length > 0);

  console.log('[OCR RAW]', lines); // 개발용: F12 콘솔에서 원문 확인 가능

  // 결과 저장할 변수들
  let store   = '';   // 교환처
  let name    = '';   // 상품명/쿠폰명
  let qty     = '';   // 수량
  let expire  = null; // 유효기간(YYYY-MM-DD)
  let code    = null; // 쿠폰번호/인증번호 등

  // 라벨 후보들
  const LABELS = {
    store:  ['교환처', '사용처', '가맹점', '교환점'],
    name:   ['상품명', '쿠폰명', '상품', '제목'],
    qty:    ['수량', '매수', '인원'],
    period: ['유효기간', '사용기간', '교환기간', '사용기한'],
    code:   ['쿠폰번호', '인증번호', 'PIN', '핀번호', '번호']
  };

  // 🔹 날짜 정규식
  //  - 2026.01.06
  //  - 2026-01-06
  //  - 26/1/6
  //  - 2026년 01월 06일
  const dateRegexGlobal =
    /(\d{2,4})\s*(?:년|[.\-\/])\s*(\d{1,2})\s*(?:월|[.\-\/])\s*(\d{1,2})\s*일?/g;

  // 🔹 날짜 뒤에 붙으면 "만료일" 힌트로 보는 키워드
  //  - ~2026년 01월 06일까지 사용
  //  - ~2026.01.06까지
  const expireHintRegex =
    /(까지\s*(사용|이용|교환|가능)?|사용기한|만료|소멸|종료|유효기간)/;

  // 보조 함수: "라벨: 값" 형태에서 값만 뽑기
  function valueAfterLabel(line, label) {
    // 예) "상품명 : 스타벅스 아메리카노 (HOT)"
    const idx = line.indexOf(label);
    if (idx === -1) return '';

    let rest = line.slice(idx + label.length);
    rest = rest.replace(/^[\s:：\-~]+/, ''); // :, :, -, ~ 등 제거
    return rest.trim();
  }

  // 보조 함수: 라벨이 있는 줄에서 안 나오면 ➜ 다음 줄을 값으로 사용
  function getLabeledValue(lines, i, label) {
    const line = lines[i];
    let val = valueAfterLabel(line, label);
    if (val) return val;

    // 다음 줄이 있으면 다음 줄을 값으로 사용
    if (i + 1 < lines.length) {
      return lines[i + 1].trim();
    }
    return '';
  }

  // 2) 한 줄씩 돌면서 라벨 매칭
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const noSpace = line.replace(/\s+/g, '');

    // 교환처
    if (!store && LABELS.store.some(k => noSpace.includes(k))) {
      for (const k of LABELS.store) {
        if (noSpace.includes(k)) {
          store = getLabeledValue(lines, i, k);
          break;
        }
      }
    }

    // 상품명
    if (!name && LABELS.name.some(k => noSpace.includes(k))) {
      for (const k of LABELS.name) {
        if (noSpace.includes(k)) {
          name = getLabeledValue(lines, i, k);
          break;
        }
      }
    }

    // 수량
    if (!qty && LABELS.qty.some(k => noSpace.includes(k))) {
      for (const k of LABELS.qty) {
        if (noSpace.includes(k)) {
          qty = getLabeledValue(lines, i, k);
          break;
        }
      }
    }

    // 쿠폰번호 / 인증번호
    if (!code && LABELS.code.some(k => noSpace.includes(k))) {
      for (const k of LABELS.code) {
        if (noSpace.includes(k)) {
          let v = getLabeledValue(lines, i, k);
          // 공백, 하이픈 제거 + 대문자
          v = v.replace(/\s+/g, '').toUpperCase();
          if (v.length >= 6) {
            code = v;
          }
          break;
        }
      }
    }
  }

  // 3) 기간(유효기간/사용기간)만 따로 모아서 "마지막 날짜"를 유효기간으로 판단
  let periodLineIndex = -1;
  for (let i = 0; i < lines.length; i++) {
    const noSpace = lines[i].replace(/\s+/g, '');
    if (LABELS.period.some(k => noSpace.includes(k))) {
      periodLineIndex = i;
      break;
    }
  }

  if (periodLineIndex !== -1) {
    let line = lines[periodLineIndex];
    let allDates = [];

    // 해당 줄에서 날짜들 다 추출 (시작~종료 구조일 수 있음)
    let m;
    while ((m = dateRegexGlobal.exec(line)) !== null) {
      allDates.push(m);
    }

    // 줄에 없으면 다음 줄에서도 찾아보기
    if (allDates.length === 0 && periodLineIndex + 1 < lines.length) {
      dateRegexGlobal.lastIndex = 0;
      const nextLine = lines[periodLineIndex + 1];
      while ((m = dateRegexGlobal.exec(nextLine)) !== null) {
        allDates.push(m);
      }
    }

    if (allDates.length > 0) {
      // 마지막 날짜를 "유효기간 종료일"로 간주
      const last = allDates[allDates.length - 1];
      let year = last[1];
      const mon = last[2].padStart(2, '0');
      const day = last[3].padStart(2, '0');

      if (year.length === 2) {
        year = '20' + year;
      }
      expire = `${year}-${mon}-${day}`;
    }
  }


   // 4) 라벨로 못 찾은 경우에 대한 Fallback (기존 방식 + '까지' 등 키워드 강화)
  // 4) 라벨로 못 찾은 경우에 대한 Fallback (기존 방식 + '까지' 등 키워드 강화)
// 4) 라벨로 못 찾은 경우에 대한 Fallback (날짜 + '까지/사용' 등 힌트 우선)
if (!expire) {
  const allCandidates = [];    // 모든 날짜
  const expireTagged  = [];    // 만료 힌트가 붙은 날짜

  for (const line of lines) {
    // OCR 결과 줄(line) 하나
    dateRegexGlobal.lastIndex = 0;
    let m;

    while ((m = dateRegexGlobal.exec(line)) !== null) {
      let year = m[1];
      const mon = m[2].padStart(2, '0');
      const day = m[3].padStart(2, '0');

      if (year.length === 2) year = '20' + year;   // 26 → 2026 같은 보정

      const dateStr = `${year}-${mon}-${day}`;
      allCandidates.push(dateStr);

      // 이 날짜 바로 뒤 몇 글자를 잘라서 '까지 사용' 등 있는지 본다
      const afterText = line.slice(m.index + m[0].length,
                                   m.index + m[0].length + 20);

      if (expireHintRegex.test(afterText) || expireHintRegex.test(line)) {
        // 예: "~2026년 01월 06일까지 사용"
        expireTagged.push(dateStr);
      }
    }
  }

  if (expireTagged.length > 0) {
    // 🔸 '까지/사용/만료' 힌트가 붙은 날짜 중 "마지막"을 만료일로 간주
    expire = expireTagged[expireTagged.length - 1];
  } else if (allCandidates.length > 0) {
    // 🔸 힌트가 없으면, 기존처럼 전체 날짜 중 "마지막"을 만료일로 간주
    expire = allCandidates[allCandidates.length - 1];
  }
}

  // 5) 쿠폰번호도 라벨로 못 찾은 경우, 예전 룰로 한 번 더 시도
  if (!code) {
    const codeRegex = /[0-9A-Z]{3,5}[\s\-]?[0-9A-Z]{3,5}[\s\-]?[0-9A-Z]{3,5}/;
    for (const line of lines) {
      const m = line.toUpperCase().match(codeRegex);
      if (m) {
        code = m[0].replace(/\s+/g, '').toUpperCase();
        break;
      }
    }
  }

  // 6) 제목 보정: 상품명이 있으면 상품명, 없으면 라벨 없는 첫 줄 중에서 길이가 적당한 것
  let title = '';
  if (name) {
    // 교환처/수량 정보도 제목에 살짝 추가해주면 나중에 보기 쉬움
    title = name;
    if (store) title = `${store} - ${title}`;
    if (qty)   title = `${title} (${qty})`;
  } else {
    // "상품명"이 없는 경우, 위쪽에서 한 줄 골라 보기
    const candidate = lines.find(l =>
      !LABELS.store.concat(LABELS.name, LABELS.qty, LABELS.period, LABELS.code)
        .some(k => l.replace(/\s+/g, '').includes(k))
    );
    if (candidate) title = candidate;
  }

  // 7) 실제 입력칸에 적용
  const titleInput = document.querySelector('input[name="title"]');
  const codeInput  = document.querySelector('input[name="coupon_code"]');
  const dateInput  = document.querySelector('input[name="expire_date"]');

  if (titleInput && title)  titleInput.value = title;
  if (codeInput && code)    codeInput.value  = code;
  if (dateInput && expire)  dateInput.value  = expire;

  // (참고용) 나중에 DB 필드를 늘리면 교환처/수량도 따로 저장 가능
  console.log('[PARSED]', { store, name, qty, expire, code });
}
</script>
</div>
</body>
</html>
