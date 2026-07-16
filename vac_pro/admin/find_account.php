<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$mode = ($_GET['mode'] ?? 'find') === 'reset' ? 'reset' : 'find';
$msg = '';
$err = '';
$result = null;

function normalize_phone_account(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function masked_username(string $username): string
{
    $len = mb_strlen($username, 'UTF-8');
    if ($len <= 2) return mb_substr($username, 0, 1, 'UTF-8') . '*';
    return mb_substr($username, 0, 2, 'UTF-8')
        . str_repeat('*', max(2, $len - 3))
        . mb_substr($username, -1, 1, 'UTF-8');
}

function temporary_password(): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $all = $upper . $lower . $digits;
    $password = $upper[random_int(0, strlen($upper) - 1)]
        . $lower[random_int(0, strlen($lower) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)];
    while (strlen($password) < 10) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    return str_shuffle($password);
}

// 간단한 세션 기반 반복 시도 제한
$now = time();
$attempt = $_SESSION['account_recovery_attempt'] ?? ['start' => $now, 'count' => 0];
if (($now - (int)$attempt['start']) > 600) $attempt = ['start' => $now, 'count' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if ((int)$attempt['count'] >= 10) {
            throw new RuntimeException('요청 횟수가 많습니다. 10분 후 다시 시도하세요.');
        }
        $attempt['count']++;
        $_SESSION['account_recovery_attempt'] = $attempt;

        $name = trim((string)($_POST['name'] ?? ''));
        $phone = normalize_phone_account((string)($_POST['phone'] ?? ''));
        if ($name === '' || strlen($phone) < 10) {
            throw new RuntimeException('이름과 휴대폰 번호를 정확히 입력하세요.');
        }

        if ($mode === 'find') {
            $stmt = db()->query("SELECT id, username, name, phone FROM admins WHERE is_active=1 ORDER BY id");
            $matches = [];
            foreach ($stmt->fetchAll() as $row) {
                if (trim((string)$row['name']) === $name
                    && normalize_phone_account((string)$row['phone']) === $phone) {
                    $matches[] = masked_username((string)$row['username']);
                }
            }
            if (!$matches) throw new RuntimeException('일치하는 관리자 계정을 찾지 못했습니다.');
            $result = ['type' => 'find', 'usernames' => $matches];
            $msg = '등록된 관리자 아이디를 확인했습니다.';
        } else {
            $username = trim((string)($_POST['username'] ?? ''));
            if ($username === '') throw new RuntimeException('아이디를 입력하세요.');

            $stmt = db()->prepare('SELECT id, username, name, phone FROM admins WHERE username=? AND is_active=1 LIMIT 1');
            $stmt->execute([$username]);
            $row = $stmt->fetch();
            if (!$row
                || trim((string)$row['name']) !== $name
                || normalize_phone_account((string)$row['phone']) !== $phone) {
                throw new RuntimeException('입력한 계정 정보가 일치하지 않습니다.');
            }

            $tempPassword = temporary_password();
            db()->prepare('UPDATE admins SET password_hash=? WHERE id=?')
                ->execute([password_hash($tempPassword, PASSWORD_DEFAULT), (int)$row['id']]);

            $result = [
                'type' => 'reset',
                'username' => (string)$row['username'],
                'password' => $tempPassword,
            ];
            $msg = '임시 비밀번호가 발급되었습니다. 로그인 후 즉시 변경하세요.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="../assets/style.css?v=<?=filemtime(__DIR__.'/../assets/style.css')?>">
    <title><?=$mode === 'find' ? '관리자 아이디 찾기' : '관리자 비밀번호 초기화'?></title>
</head>
<body>
<div class="login account-recovery">
    <div class="card">
        <h1><?=$mode === 'find' ? '관리자 아이디 찾기' : '관리자 비밀번호 초기화'?></h1>
        <p class="muted">권한계정에 등록된 이름과 휴대폰 번호를 기준으로 확인합니다.</p>

        <div class="recovery-tabs">
            <a class="<?=$mode === 'find' ? 'active' : ''?>" href="find_account.php?mode=find">아이디 찾기</a>
            <a class="<?=$mode === 'reset' ? 'active' : ''?>" href="find_account.php?mode=reset">비밀번호 초기화</a>
        </div>

        <?php if ($msg): ?><div class="alert ok"><?=h($msg)?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert err"><?=h($err)?></div><?php endif; ?>

        <?php if ($result && $result['type'] === 'find'): ?>
            <div class="recovery-result">
                <strong>확인된 아이디</strong>
                <?php foreach ($result['usernames'] as $username): ?>
                    <div class="temp-credential"><?=h($username)?></div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($result && $result['type'] === 'reset'): ?>
            <div class="recovery-result">
                <div><span>아이디</span><strong><?=h($result['username'])?></strong></div>
                <div><span>임시 비밀번호</span><strong class="temp-credential"><?=h($result['password'])?></strong></div>
                <p class="muted">이 화면을 닫으면 임시 비밀번호를 다시 확인할 수 없습니다.</p>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <?php if ($mode === 'reset'): ?>
                <label>아이디<input name="username" required autocomplete="username" value="<?=h($_POST['username'] ?? '')?>"></label>
            <?php endif; ?>
            <label>이름<input name="name" required value="<?=h($_POST['name'] ?? '')?>"></label>
            <label>휴대폰 번호<input name="phone" required inputmode="numeric" placeholder="01012345678" value="<?=h($_POST['phone'] ?? '')?>"></label>
            <button class="btn" type="submit"><?=$mode === 'find' ? '아이디 확인' : '임시 비밀번호 발급'?></button>
        </form>
        <div class="login-help"><a href="login.php">관리자 로그인으로 돌아가기</a></div>
    </div>
</div>
</body>
</html>
