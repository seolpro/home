<?php
require_once __DIR__.'/config.php';

$eyebrow = get_setting('site_eyebrow', 'EDUCATION VIDEO');
$title = get_setting('site_title', '교육영상 모음');
$subtitle = get_setting('site_subtitle', '이 영상 자료는 교육 관련 보조자료로 활용해 주세요.');
$max = max(1, (int)get_setting('max_videos', '20'));

$stmt = db()->prepare('SELECT * FROM yt_videos WHERE is_active=1 ORDER BY sort_order ASC, id DESC LIMIT '.$max);
$stmt->execute();
$videos = $stmt->fetchAll();

if (count($videos) === 1) {
    header('Location: play.php?code='.rawurlencode($videos[0]['slug']));
    exit;
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=h($title)?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="hero">
  <div>
    <p class="eyebrow"><?=h($eyebrow)?></p>
    <h1><?=h($title)?></h1>
    <p><?=h($subtitle)?></p>
  </div>
  <a href="admin/login.php" class="admin-btn">⚙ 관리자</a>
</header>

<main class="wrap grid">
  <?php if (!$videos): ?>
    <div class="empty">등록된 영상이 없습니다. 관리자 페이지에서 영상을 등록하세요.</div>
  <?php endif; ?>

  <?php foreach($videos as $v): ?>
    <a class="card" href="play.php?code=<?=rawurlencode($v['slug'])?>">
      <img src="https://img.youtube.com/vi/<?=h($v['youtube_id'])?>/hqdefault.jpg" alt="">
      <div class="card-body">
        <strong><?=h($v['title'])?></strong>
        <span>▶ 영상 바로 재생</span>
      </div>
    </a>
  <?php endforeach; ?>
</main>

<footer class="footer">
  <small>일부 영상은 유튜버 영상으로 대체 링크되어 있습니다.</small>
</footer>
</body>
</html>
