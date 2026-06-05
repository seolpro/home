<?php
require_once __DIR__.'/../config.php';
require_admin();
$videos = db()->query('SELECT * FROM yt_videos ORDER BY sort_order ASC, id DESC')->fetchAll();
$csrf = csrf_token();
$base = base_url();
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>영상 관리자</title>
  <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<nav>
  <strong>영상 관리자</strong>
  <span>
    <a href="settings.php">🛠 환경설정</a>
    <a href="../index.php" target="_blank">👤 사용자화면</a>
    <a href="logout.php">로그아웃</a>
  </span>
</nav>

<main class="container">
  <section class="panel">
    <h2>영상 등록/수정</h2>
    <form method="post" action="save.php" class="form">
      <input type="hidden" name="csrf" value="<?=h($csrf)?>">
      <input type="hidden" name="id" id="id">

      <label>제목<input name="title" id="title" required placeholder="예: NIHSS 교육영상"></label>
      <label>유튜브 링크 또는 영상ID<input name="youtube_url" id="youtube_url" required placeholder="https://youtu.be/EH5TzIDTu0M"></label>
      <label>접속코드/슬러그<input name="slug" id="slug" required placeholder="nihss"></label>
      <label>정렬순서<input type="number" name="sort_order" id="sort_order" value="0"></label>
      <label class="wide">설명<textarea name="description" id="description" rows="3"></textarea></label>
      <label class="check"><input type="checkbox" name="is_active" id="is_active" value="1" checked> 사용</label>

      <button type="submit">저장</button>
      <button type="button" onclick="resetForm()" class="ghost">새로 입력</button>
    </form>
  </section>

  <section class="panel">
    <h2>등록된 영상</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>순서</th>
            <th>제목</th>
            <th>영상ID</th>
            <th>PPT 연결주소</th>
            <th>상태</th>
            <th>관리</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($videos as $v): $url=$base.'/play.php?code='.rawurlencode($v['slug']); ?>
          <tr>
            <td><?=h((string)$v['sort_order'])?></td>
            <td><?=h($v['title'])?></td>
            <td><code><?=h($v['youtube_id'])?></code></td>
            <td>
              <div class="copy-wrap">
                <input class="copy" value="<?=h($url)?>" readonly onclick="this.select()">
                <button type="button" class="copy-btn" data-url="<?=h($url)?>">URL복사</button>
              </div>
            </td>
            <td><?=((int)$v['is_active']===1?'사용':'숨김')?></td>
            <td class="actions">
              <button type="button" onclick='editVideo(<?=json_encode($v, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>)'>수정</button>
              <form method="post" action="delete.php" onsubmit="return confirm('삭제할까요?')" style="display:inline">
                <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                <input type="hidden" name="id" value="<?=h((string)$v['id'])?>">
                <button class="danger">삭제</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script>
function editVideo(v){
  id.value = v.id;
  title.value = v.title;
  youtube_url.value = v.youtube_url;
  slug.value = v.slug;
  sort_order.value = v.sort_order;
  description.value = v.description || '';
  is_active.checked = String(v.is_active) === '1';
  scrollTo({top:0, behavior:'smooth'});
}
function resetForm(){
  document.querySelector('.form').reset();
  id.value = '';
  is_active.checked = true;
}
document.querySelectorAll('.copy-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const url = btn.dataset.url;
    try {
      await navigator.clipboard.writeText(url);
      const old = btn.textContent;
      btn.textContent = '복사완료';
      btn.classList.add('copied');
      setTimeout(() => { btn.textContent = old; btn.classList.remove('copied'); }, 1500);
    } catch (e) {
      alert('복사에 실패했습니다. 주소를 직접 선택해서 복사해주세요.');
    }
  });
});
</script>
</body>
</html>
