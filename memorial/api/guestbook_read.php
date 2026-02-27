<?php
// memorial/api/guestbook_read.php
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['ok' => false, 'msg' => 'POST only'], 405);
}

$id = (int)($_POST['id'] ?? 0);
$password = trim($_POST['password'] ?? '');

if ($id <= 0) json_response(['ok' => false, 'msg' => '잘못된 요청입니다.'], 400);

$stmt = $conn->prepare("SELECT id, name, message, media_url, is_private, password_hash, created_at
                        FROM memorial_guestbook WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) json_response(['ok' => false, 'msg' => '글을 찾을 수 없습니다.'], 404);

$is_private = (int)$row['is_private'];

if ($is_private === 1) {
  if ($password === '') json_response(['ok' => false, 'msg' => '비밀번호가 필요합니다.'], 400);

  if (!$row['password_hash'] || !password_verify($password, $row['password_hash'])) {
    json_response(['ok' => false, 'msg' => '비밀번호가 일치하지 않습니다.'], 403);
  }
}

// 성공 시 원문 반환
json_response([
  'ok' => true,
  'item' => [
    'id' => (int)$row['id'],
    'name' => $row['name'],
    'message' => $row['message'],
    'media_url' => $row['media_url'],
    'is_private' => $is_private,
    'created_at' => $row['created_at'],
  ]
]);