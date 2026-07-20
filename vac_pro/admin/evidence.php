<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/app.php';
require_admin();

$requestId = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT lr.id,lr.evidence_path,lr.evidence_name,lr.evidence_mime,lr.evidence_size,e.department_id FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.id=? LIMIT 1");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request || empty($request['evidence_path'])) { http_response_code(404); exit('증빙서류를 찾을 수 없습니다.'); }

$allowed = can_manage_hr() || can_view_department((int)$request['department_id']);
if (!$allowed && admin_employee_id() > 0) {
    $check = db()->prepare('SELECT COUNT(*) FROM request_approvals WHERE request_id=? AND approver_employee_id=?');
    $check->execute([$requestId, admin_employee_id()]);
    $allowed = (int)$check->fetchColumn() > 0;
}
if (!$allowed) { http_response_code(403); exit('증빙서류를 확인할 권한이 없습니다.'); }

$base = realpath(dirname(__DIR__) . '/uploads/evidence');
$file = realpath(dirname(__DIR__) . '/' . ltrim((string)$request['evidence_path'], '/'));
if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit('증빙서류 파일이 존재하지 않습니다.'); }

$mime = (string)($request['evidence_mime'] ?: 'application/octet-stream');
$name = (string)($request['evidence_name'] ?: basename($file));
$disposition = (str_starts_with($mime, 'image/') || $mime === 'application/pdf') ? 'inline' : 'attachment';
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header("Content-Disposition: {$disposition}; filename*=UTF-8''" . rawurlencode($name));
header('Cache-Control: private, no-store, max-age=0');
readfile($file);
exit;
