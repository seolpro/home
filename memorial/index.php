<?php /* memorial/index.php */ ?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>어머니 추모관</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/memorial.css" rel="stylesheet">
</head>
<body>

<header class="memorial-hero">
  <div class="container py-5">
    <div class="hero-card mx-auto">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="candle" aria-hidden="true"></div>
        <div>
          <h1 class="h3 mb-1">어머니 추모관</h1>
          <div class="text-muted small">기억과 사랑을 남기는 공간</div>
        </div>
      </div>
      <div class="quote">“사랑은 기억 속에서 더 선명해집니다.”</div>
    </div>
  </div>
</header>

<main class="container my-4 my-lg-5">

  <section class="mb-4">
    <div class="section-title mb-2">추모 영상</div>
    <div class="card memorial-card">
      <div class="card-body">
        <div class="ratio ratio-16x9 rounded overflow-hidden">
  <div class="video-wrap">
    <video id="memorialVideo" autoplay muted playsinline controls preload="metadata">
      <source src="uploads/mom_memorial_sm.mp4" type="video/mp4">
    </video>
    <button type="button" id="soundBtn" class="sound-btn">🔊 소리 켜기</button>
  </div>
</div>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <a class="btn btn-outline-danger btn-sm"
             href="https://youtu.be/Rvl1ovXN7Ss" target="_blank" rel="noopener">▶ 유튜브로 보기</a>
          <span class="text-muted small align-self-center">영상은 서버 mp4로 재생됩니다.</span>
        </div>
      </div>
    </div>
  </section>

  <section class="mb-4">
    <div class="section-title mb-2">추모글 남기기</div>
    <div class="text-muted small mb-2">추모글을 남기세요.</div>

    <div class="card memorial-card">
      <div class="card-body">
        <form id="guestbookForm" enctype="multipart/form-data">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">추모자</label>
              <input name="name" class="form-control" maxlength="50" placeholder="성함 또는 별명 (비우면 익명)">
            </div>

            <div class="col-12">
              <label class="form-label">추모글</label>
              <textarea name="message" class="form-control" rows="4" maxlength="3000" required
                placeholder="따뜻한 기억을 남겨주세요."></textarea>
            </div>

            <div class="col-12">
              <label class="form-label">사진(파일) 업로드 (선택)</label>
              <input type="file" name="media" class="form-control" accept="image/*">
              <div class="form-text">사진 1장 (5MB 이하)</div>
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-memorial" type="submit" id="submitBtn">✅추모글 등록</button>
              <div class="text-muted small align-self-center" id="statusText"></div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </section>

  <section class="mb-5">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="section-title">추모글</div>
      <div class="text-muted small" id="countText"></div>
    </div>

    <div id="list" class="vstack gap-3"></div>

    <nav class="mt-4">
      <ul class="pagination pagination-sm justify-content-center" id="pager"></ul>
    </nav>
  </section>
</main>

<footer class="py-4 text-center text-muted small">© Memorial</footer>

<!-- (선택) Bootstrap JS: 지금 코드엔 필수 아님 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const video = document.getElementById('memorialVideo');
  const btn = document.getElementById('soundBtn');

  // 1) 자동재생(무음) 시도
  async function tryAutoplayMuted() {
    try {
      video.muted = true;
      await video.play();
    } catch (e) {
      // 자동재생이 막혀도, 버튼 클릭으로 재생되게 처리
      console.log('autoplay blocked:', e);
    }
  }

  // 2) 버튼 클릭 시: 소리 켜고 재생 보장
  btn.addEventListener('click', async () => {
    try {
      video.muted = false;
      video.volume = 1.0;
      await video.play();
      btn.classList.add('hide');
      btn.disabled = true;
    } catch (e) {
      console.log('unmute/play blocked:', e);
      // 그래도 막히면 안내 문구로 변경
      btn.textContent = '🔊 소리를 켜려면 영상 화면을 한 번 눌러주세요';
    }
  });

  // 3) 사용자가 컨트롤로 직접 음소거 해제한 경우 버튼 숨김
  video.addEventListener('volumechange', () => {
    if (!video.muted && video.volume > 0) {
      btn.classList.add('hide');
      btn.disabled = true;
    }
  });

  // 4) 영상 재생 시작하면(무음이라도) 버튼은 유지 (원하면 여기서 숨길 수도 있음)
  tryAutoplayMuted();
</script>

<script>
  const listEl = document.getElementById('list');
  const pagerEl = document.getElementById('pager');
  const countText = document.getElementById('countText');
  const statusText = document.getElementById('statusText');
  const submitBtn = document.getElementById('submitBtn');
  const form = document.getElementById('guestbookForm');

  const escapeHtml = (s) => (s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[m]));

  async function load(page=1) {
    const size = 10;
    const res = await fetch(`api/guestbook_list.php?page=${page}&size=${size}`);
    const data = await res.json();
    if (!data.ok) return;

    countText.textContent = `총 ${data.total.toLocaleString()}건`;

    listEl.innerHTML = data.items.map(item => {
      const created = new Date(String(item.created_at).replace(' ', 'T'));
      const dateText = isNaN(created) ? item.created_at : created.toLocaleString('ko-KR');

      const media = item.media_url
        ? `<div class="mt-2"><img class="img-fluid rounded memorial-img" src="${escapeHtml(item.media_url)}" alt="첨부 이미지"></div>`
        : '';

      return `
        <div class="card memorial-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="fw-semibold">${escapeHtml(item.name)}</div>
                <div class="text-muted small">${escapeHtml(dateText)}</div>
              </div>
            </div>
            <div class="mt-3 prewrap">${escapeHtml(item.message)}</div>
            ${media}
          </div>
        </div>
      `;
    }).join('');

    const totalPages = Math.max(1, Math.ceil(data.total / data.size));
    pagerEl.innerHTML = '';
    const mk = (p, label, active=false, disabled=false) => {
      const li = document.createElement('li');
      li.className = `page-item ${active?'active':''} ${disabled?'disabled':''}`;
      li.innerHTML = `<a class="page-link" href="#">${label}</a>`;
      li.onclick = (e) => { e.preventDefault(); if(!disabled) load(p); };
      pagerEl.appendChild(li);
    };

    mk(Math.max(1, data.page-1), '«', false, data.page===1);
    const start = Math.max(1, data.page-2);
    const end = Math.min(totalPages, data.page+2);
    for (let p=start; p<=end; p++) mk(p, String(p), p===data.page);
    mk(Math.min(totalPages, data.page+1), '»', false, data.page===totalPages);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    statusText.textContent = '등록 중...';
    submitBtn.disabled = true;

    try {
      const fd = new FormData(form);
      const res = await fetch('api/guestbook_add.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.ok) {
        statusText.textContent = '등록 완료되었습니다.';
        form.reset();
        await load(1);
      } else {
        statusText.textContent = data.msg || '등록 실패';
      }
    } catch (err) {
      statusText.textContent = '오류가 발생했습니다.';
    } finally {
      submitBtn.disabled = false;
      setTimeout(()=> statusText.textContent='', 2500);
    }
  });

  load(1);
</script>

</body>
</html>