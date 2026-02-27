<?php
// memorial/api/guestbook_list.php
require_once __DIR__ . '/../db.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$size = min(30, max(5, (int)($_GET['size'] ?? 10)));
$offset = ($page - 1) * $size;

// total
$totalRes = $conn->query("SELECT COUNT(*) AS cnt FROM memorial_guestbook");
$totalRow = $totalRes->fetch_assoc();
$total = (int)($totalRow['cnt'] ?? 0);

// list
$stmt = $conn->prepare("
  SELECT id, name, message, media_url, created_at
  FROM memorial_guestbook
  ORDER BY id DESC
  LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $size, $offset);
$stmt->execute();

// mysqlnd 없이도 동작하는 방식
$stmt->bind_result($id, $name, $message, $media_url, $created_at);

$rows = [];
while ($stmt->fetch()) {
  $rows[] = [
    'id' => (int)$id,
    'name' => $name,
    'message' => $message,
    'media_url' => $media_url,
    'created_at' => $created_at,
  ];
}
$stmt->close();

json_response([
  'ok' => true,
  'page' => $page,
  'size' => $size,
  'total' => $total,
  'items' => $rows
]);