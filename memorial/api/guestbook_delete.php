<?php
// memorial/api/guestbook_delete.php
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['ok' => false, 'msg' => 'POST only'], 405);
}

$id = (int)($_POST['id'] ?? 0);
$password = trim($_POST['password'] ?? '');

if ($id <= 0) json_response(['ok' => false, 'msg' => '잘못된 요청입니다.'], 400);
if ($password === '') json_response(['ok' => false, 'msg' => '비밀번호가 필요합니다.'], 400);

// 글 조회
$stmt = $conn->prepare("SELECT id, password_hash, media_url FROM memorial_guestbook WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) json_response(['ok' => false, 'msg' => '글을 찾을 수 없습니다.'], 404);

// 비밀번호가 저장되지 않은 글은 삭제 불가(사용자가 비번 없이 등록한 경우)
if (!$row['password_hash']) {
  json_response(['ok' => false, 'msg' => '이 글은 비밀번호가 설정되지 않아 삭제할 수 없습니다.'], 403);
}

if (!password_verify($password, $row['password_hash'])) {
  json_response(['ok' => false, 'msg' => '비밀번호가 일치하지 않습니다.'], 403);
}

// DB 삭제
$del = $conn->prepare("DELETE FROM memorial_guestbook WHERE id=?");
$del->bind_param("i", $id);
$del->execute();

// 첨부 이미지 파일도 함께 삭제(선택)
if (!empty($row['media_url'])) {
  $path = realpath(__DIR__ . '/../' . $row['media_url']);
  $uploads = realpath(__DIR__ . '/../uploads');
  // uploads 폴더 내부 파일만 삭제하도록 안전장치
  if ($path && $uploads && str_starts_with($path, $uploads)) {
    @unlink($path);
  }
}

json_response(['ok' => true, 'msg' => '삭제되었습니다.']);