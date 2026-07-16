<?php
require_once __DIR__.'/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function admin_user(): ?array { return $_SESSION['admin'] ?? null; }
function require_admin(): void {
    if (!admin_user()) {
        $base = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? 'login.php' : 'admin/login.php';
        header('Location: '.$base);
        exit;
    }
}
function has_role(array $roles): bool {
    $u = admin_user();
    return $u && in_array((string)($u['role'] ?? ''), $roles, true);
}
function is_super_admin(): bool { return has_role(['super_admin']); }
function can_manage_hr(): bool { return has_role(['super_admin','hr_admin']); }
function can_approve(): bool { return has_role(['super_admin','hr_admin','department_manager','approver']); }
function admin_employee_id(): int { return (int)(admin_user()['employee_id'] ?? 0); }
function require_super_admin(): void { require_admin(); if (!is_super_admin()) { http_response_code(403); exit('최고관리자 권한이 필요합니다.'); } }
function require_hr_admin(): void { require_admin(); if (!can_manage_hr()) { http_response_code(403); exit('인사관리 권한이 필요합니다.'); } }
function require_approver(): void { require_admin(); if (!can_approve()) { http_response_code(403); exit('결재 권한이 필요합니다.'); } }
function accessible_department_ids(): array {
    static $ids = null;
    if ($ids !== null) return $ids;
    if (can_manage_hr()) return $ids = [];
    $u = admin_user();
    if (!$u) return $ids = [-1];
    $q = db()->prepare('SELECT department_id FROM admin_department_scopes WHERE admin_id=?');
    $q->execute([(int)$u['id']]);
    $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids && !empty($u['employee_id'])) {
        $q = db()->prepare('SELECT department_id FROM employees WHERE id=?');
        $q->execute([(int)$u['employee_id']]);
        $d = (int)$q->fetchColumn();
        if ($d) $ids = [$d];
    }
    return $ids ?: [-1];
}
function can_view_department(int $departmentId): bool {
    return can_manage_hr() || in_array($departmentId, accessible_department_ids(), true);
}
