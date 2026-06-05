<?php
require_once __DIR__.'/config.php';

$code = trim((string)($_GET['code'] ?? ''));
$id = trim((string)($_GET['v'] ?? ''));

if ($code !== '') {
    $stmt = db()->prepare('SELECT * FROM yt_videos WHERE slug=? AND is_active=1 LIMIT 1');
    $stmt->execute([$code]);
    $video = $stmt->fetch();
} elseif ($id !== '' && preg_match('~^[A-Za-z0-9_-]{11}$~', $id)) {
    $stmt = db()->prepare('SELECT * FROM yt_videos WHERE youtube_id=? AND is_active=1 LIMIT 1');
    $stmt->execute([$id]);
    $video = $stmt->fetch() ?: ['title'=>'YouTube 영상','youtube_id'=>$id,'description'=>''];
} else {
    $video = null;
}

if (!$video) {
    http_response_code(404);
    exit('영상을 찾을 수 없습니다.');
}

$autoplay = get_setting('autoplay','1') === '1' ? '1' : '0';
$mute = get_setting('mute','0') === '1' ? '1' : '0';
$embed = 'https://www.youtube.com/embed/'.rawurlencode($video['youtube_id'])
    .'?autoplay='.$autoplay
    .'&mute='.$mute
    .'&rel=0&modestbranding=1&playsinline=1&enablejsapi=1';
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?=h($video['title'])?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="player-page">
<main class="player-wrap">
  <section class="player-card">
    <div class="player-head">
      <div>
        <p class="eyebrow">YouTube Player</p>
        <h1><?=h($video['title'])?></h1>
      </div>
      <a class="home" href="index.php">목록</a>
    </div>

    <div class="video-box">
      <iframe
        id="ytFrame"
        src="<?=h($embed)?>"
        title="<?=h($video['title'])?>"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen>
      </iframe>
    </div>

    <?php if(!empty($video['description'])): ?>
      <p class="desc"><?=nl2br(h($video['description']))?></p>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
