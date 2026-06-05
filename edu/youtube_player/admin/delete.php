<?php
require_once __DIR__.'/../config.php';
require_admin();
verify_csrf();
$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = db()->prepare('DELETE FROM yt_videos WHERE id=?');
    $stmt->execute([$id]);
}
header('Location: index.php');
exit;
