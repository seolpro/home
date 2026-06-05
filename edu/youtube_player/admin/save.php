<?php
require_once __DIR__.'/../config.php';
require_admin();
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$youtube_url = trim((string)($_POST['youtube_url'] ?? ''));
$youtube_id = extract_youtube_id($youtube_url);
$slug = slugify((string)($_POST['slug'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$sort_order = (int)($_POST['sort_order'] ?? 0);
$is_active = isset($_POST['is_active']) ? 1 : 0;

if ($title === '' || !$youtube_id || $slug === '') {
    exit('제목, 유튜브 링크/영상ID, 접속코드를 확인하세요.');
}

try {
    if ($id > 0) {
        $stmt = db()->prepare('UPDATE yt_videos SET title=?, youtube_url=?, youtube_id=?, slug=?, description=?, sort_order=?, is_active=? WHERE id=?');
        $stmt->execute([$title, $youtube_url, $youtube_id, $slug, $description, $sort_order, $is_active, $id]);
    } else {
        $stmt = db()->prepare('INSERT INTO yt_videos (title, youtube_url, youtube_id, slug, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $youtube_url, $youtube_id, $slug, $description, $sort_order, $is_active]);
    }
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) == 1062) {
        exit('이미 사용 중인 접속코드/슬러그입니다. 다른 코드를 입력하세요.');
    }
    throw $e;
}

header('Location: index.php');
exit;
