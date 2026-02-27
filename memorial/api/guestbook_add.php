<?php
// memorial/api/guestbook_add.php
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['ok' => false, 'msg' => 'POST only'], 405);
}

$name = trim($_POST['name'] ?? '');
$message = trim($_POST['message'] ?? '');

// 이름 정책: 비우면 익명 처리(원치 않으면 아래 한 줄을 "필수"로 바꾸면 됨)
if ($name === '') $name = '익명';

if ($message === '') {
  json_response(['ok' => false, 'msg' => '추모글은 필수입니다.'], 400);
}

if (mb_strlen($name) > 50) $name = mb_substr($name, 0, 50);
if (mb_strlen($message) > 3000) $message = mb_substr($message, 0, 3000);

$media_url = null;

// (선택) 이미지 업로드
if (!empty($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE) {
  if ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'msg' => '파일 업로드 오류가 발생했습니다.'], 400);
  }

  $maxBytes = 5 * 1024 * 1024; // 5MB
  if ($_FILES['media']['size'] > $maxBytes) {
    json_response(['ok' => false, 'msg' => '파일은 5MB 이하만 가능합니다.'], 400);
  }

  $original = $_FILES['media']['name'];
  $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

  $allowed = ['jpg','jpeg','png','webp','gif'];
  if (!in_array($ext, $allowed, true)) {
    json_response(['ok' => false, 'msg' => '이미지 파일(jpg/png/webp/gif)만 가능합니다.'], 400);
  }

  $newName = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $destPath = __DIR__ . '/../uploads/' . $newName;

  if (!move_uploaded_file($_FILES['media']['tmp_name'], $destPath)) {
    json_response(['ok' => false, 'msg' => '파일 저장에 실패했습니다.'], 500);
  }

  $media_url = 'uploads/' . $newName;
}

// is_private/password_hash는 사용 안 함 → NULL/0으로 저장
$stmt = $conn->prepare("
  INSERT INTO memorial_guestbook (name, password_hash, message, media_url, is_private)
  VALUES (?, NULL, ?, ?, 0)
");
$stmt->bind_param("sss", $name, $message, $media_url);
$stmt->execute();

json_response(['ok' => true, 'msg' => '추모글이 등록되었습니다.']);