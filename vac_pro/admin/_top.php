<?php
require_once __DIR__ . '/../lib/auth.php';
require_admin();

$pageTitle   = $pageTitle ?? '관리자';
$orgName     = setting('org_name', '휴가관리시스템');
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

function admin_nav_active(string $file, string $currentPage): string {
    return $file === $currentPage ? ' active' : '';
}

$inboxCount = 0;
if (can_approve() && admin_employee_id() > 0) {
    try {
        $q = db()->prepare("
            SELECT COUNT(*)
            FROM request_approvals ra
            JOIN leave_requests lr ON lr.id = ra.request_id
            WHERE ra.approver_employee_id = ?
              AND ra.status = 'pending'
              AND ra.step_order = lr.current_step
              AND lr.status IN ('pending', 'in_approval')
        ");
        $q->execute([admin_employee_id()]);
        $inboxCount = (int)$q->fetchColumn();
    } catch (Throwable $e) {
        $inboxCount = 0;
    }
}

$adminName = (string)($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? '관리자');
$adminRole = function_exists('admin_role_label')
    ? admin_role_label()
    : (string)($_SESSION['admin_role'] ?? '관리자');
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?=h($pageTitle)?> | <?=h($orgName)?></title>
    <link rel="stylesheet" href="../assets/style.css?v=<?=filemtime(__DIR__.'/../assets/style.css')?>">
</head>
<body>

<header class="smart-admin-header">
    <div class="smart-admin-head-inner">
        <a class="smart-brand" href="dashboard.php">
            <span class="smart-brand-mark">H</span>
            <span class="smart-brand-copy">
                <strong><?=h($orgName)?></strong>
                <small>HRM Plus 관리자</small>
            </span>
        </a>

        <div class="smart-admin-user">
            <?php if (can_approve()): ?>
    <a class="smart-inbox-link" href="../index.php">
        <span>📝 신청페이지</span>
    </a>

    <a class="smart-inbox-link" href="inbox.php">
        <span>📥 내 결재함</span>
    </a>
<?php endif; ?>

            <div class="smart-user-avatar"><?=h(mb_substr($adminName, 0, 1))?></div>
            <div class="smart-user-meta">
                <strong><?=h($adminName)?></strong>
                <small><?=h($adminRole)?></small>
            </div>
            <a class="smart-logout" href="logout.php">로그아웃</a>
            <button type="button" class="smart-menu-toggle" aria-label="메뉴 열기" aria-expanded="false">☰</button>
        </div>
    </div>

    <div class="smart-admin-nav-wrap">
        <nav class="smart-admin-nav" id="smartAdminNav" aria-label="관리자 메뉴">
            <a class="<?=admin_nav_active('dashboard.php', $currentPage)?>" href="dashboard.php">대시보드</a>

            <?php if (can_approve()): ?>
                <a class="<?=admin_nav_active('inbox.php', $currentPage)?>" href="inbox.php">
                    내 결재함
                    <?php if ($inboxCount > 0): ?><span class="nav-count"><?=$inboxCount?></span><?php endif ?>
                </a>
            <?php endif ?>

            <a class="<?=admin_nav_active('requests.php', $currentPage)?>" href="requests.php">신청·결재</a>
            <a class="<?=admin_nav_active('leave_status.php', $currentPage)?>" href="leave_status.php">휴가현황</a>

            <?php if (can_manage_hr()): ?>
                <a class="<?=admin_nav_active('employees.php', $currentPage)?>" href="employees.php">직원</a>
                <a class="<?=admin_nav_active('departments.php', $currentPage)?>" href="departments.php">부서</a>
                <a class="<?=admin_nav_active('leave_types.php', $currentPage)?>" href="leave_types.php">휴가유형</a>
                <a class="<?=admin_nav_active('approval_lines.php', $currentPage)?>" href="approval_lines.php">결재선</a>
                <a class="<?=admin_nav_active('allowances.php', $currentPage)?>" href="allowances.php">연차수당</a>
                <a class="<?=admin_nav_active('import.php', $currentPage)?>" href="import.php">일괄업로드</a>
                <a class="<?=admin_nav_active('notifications.php', $currentPage)?>" href="notifications.php">알림톡</a>
                <a class="<?=admin_nav_active('users.php', $currentPage)?>" href="users.php">권한계정</a>
                <a class="<?=admin_nav_active('settings.php', $currentPage)?>" href="settings.php">설정</a>
                <a class="<?=admin_nav_active('logs.php', $currentPage)?>" href="logs.php">로그</a>
            <?php endif ?>
        </nav>
    </div>
</header>

<main class="wrap admin-main">

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.smart-menu-toggle');
    const nav = document.getElementById('smartAdminNav');

    if (!toggle || !nav) return;

    toggle.addEventListener('click', function () {
        const opened = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
    });
});
</script>
