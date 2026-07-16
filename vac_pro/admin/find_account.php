<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/account_recovery.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

account_recovery_ensure_template();

$mode = ($_GET['mode'] ?? 'find') === 'reset' ? 'reset' : 'find';
$msg = '';
$err = '';
$result = null;

function masked_username(string $username): string
{
    $len = mb_strlen($username, 'UTF-8');

    if ($len <= 2) {
        return mb_substr($username, 0, 1, 'UTF-8') . '*';
    }

    return mb_substr($username, 0, 2, 'UTF-8')
        . str_repeat('*', max(2, $len - 3))
        . mb_substr($username, -1, 1, 'UTF-8');
}

// 세션 기준 10분 동안 최대 10회
$now = time();
$attempt = $_SESSION['account_recovery_attempt']
    ?? ['start' => $now, 'count' => 0];

if (($now - (int)$attempt['start']) > 600) {
    $attempt = ['start' => $now, 'count' => 0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        if ((int)$attempt['count'] >= 10) {
            throw new RuntimeException(
                '요청 횟수가 많습니다. 10분 후 다시 시도하세요.'
            );
        }

        $attempt['count']++;
        $_SESSION['account_recovery_attempt'] = $attempt;

        $name = trim((string)($_POST['name'] ?? ''));
        $phone = account_recovery_phone(
            (string)($_POST['phone'] ?? '')
        );

        if ($name === '' || strlen($phone) < 10) {
            throw new RuntimeException(
                '이름과 휴대폰 번호를 정확히 입력하세요.'
            );
        }

        if ($mode === 'find') {
            $stmt = db()->query("
                SELECT id, username, name, phone
                FROM admins
                WHERE is_active = 1
                ORDER BY id
            ");

            $matches = [];

            foreach ($stmt->fetchAll() as $row) {
                if (
                    trim((string)$row['name']) === $name
                    && account_recovery_phone(
                        (string)$row['phone']
                    ) === $phone
                ) {
                    $matches[] = masked_username(
                        (string)$row['username']
                    );
                }
            }

            if (!$matches) {
                throw new RuntimeException(
                    '일치하는 관리자 계정을 찾지 못했습니다.'
                );
            }

            $result = [
                'type' => 'find',
                'usernames' => $matches,
            ];

            $msg = '등록된 관리자 아이디를 확인했습니다.';
        } else {
            $username = trim(
                (string)($_POST['username'] ?? '')
            );

            if ($username === '') {
                throw new RuntimeException('아이디를 입력하세요.');
            }

            $stmt = db()->prepare("
                SELECT
                    id,
                    username,
                    password_hash,
                    name,
                    phone,
                    alimtalk_opt_in,
                    is_active
                FROM admins
                WHERE username = ?
                  AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $row = $stmt->fetch();

            if (
                !$row
                || trim((string)$row['name']) !== $name
                || account_recovery_phone(
                    (string)$row['phone']
                ) !== $phone
            ) {
                throw new RuntimeException(
                    '입력한 계정 정보가 일치하지 않습니다.'
                );
            }

            $temporaryPassword =
                account_recovery_temporary_password();

            $pdo = db();
            $pdo->beginTransaction();

            try {
                // 알림톡 발송이 성공한 경우에만 비밀번호를 확정합니다.
                $pdo->prepare("
                    UPDATE admins
                    SET password_hash = ?
                    WHERE id = ?
                ")->execute([
                    password_hash(
                        $temporaryPassword,
                        PASSWORD_DEFAULT
                    ),
                    (int)$row['id'],
                ]);

                $sent = account_recovery_send_password_alimtalk(
                    $row,
                    $temporaryPassword
                );

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            $result = [
                'type' => 'reset',
                'masked_phone' => $sent['masked_phone'],
            ];

            $msg = '등록된 휴대폰으로 임시 비밀번호를 발송했습니다.';
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
    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >
    <meta name="robots" content="noindex,nofollow">
    <link
        rel="stylesheet"
        href="../assets/style.css?v=<?=filemtime(__DIR__.'/../assets/style.css')?>"
    >
    <title>
        <?=$mode === 'find'
            ? '관리자 아이디 찾기'
            : '관리자 비밀번호 초기화'?>
    </title>
</head>
<body>
<div class="login account-recovery">
    <div class="card">
        <div class="recovery-heading">
            <span class="recovery-mark">
                <?=$mode === 'find' ? 'ID' : 'PW'?>
            </span>
            <div>
                <h1>
                    <?=$mode === 'find'
                        ? '관리자 아이디 찾기'
                        : '관리자 비밀번호 초기화'?>
                </h1>
                <p class="muted">
                    권한계정에 등록된 이름과 휴대폰 번호로 확인합니다.
                </p>
            </div>
        </div>

        <div class="recovery-tabs">
            <a
                class="<?=$mode === 'find' ? 'active' : ''?>"
                href="find_account.php?mode=find"
            >아이디 찾기</a>
            <a
                class="<?=$mode === 'reset' ? 'active' : ''?>"
                href="find_account.php?mode=reset"
            >비밀번호 초기화</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert ok"><?=h($msg)?></div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div class="alert err"><?=h($err)?></div>
        <?php endif; ?>

        <?php if ($result && $result['type'] === 'find'): ?>
            <div class="recovery-result">
                <strong>확인된 아이디</strong>
                <?php foreach ($result['usernames'] as $username): ?>
                    <div class="temp-credential">
                        <?=h($username)?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($result && $result['type'] === 'reset'): ?>
            <div class="recovery-result secure-result">
                <strong>알림톡 발송 완료</strong>
                <p>
                    <?=h($result['masked_phone'])?> 번호로
                    임시 비밀번호를 발송했습니다.
                </p>
                <p class="muted">
                    로그인 후 안전한 비밀번호로 변경해 주세요.
                </p>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input
                type="hidden"
                name="csrf"
                value="<?=csrf_token()?>"
            >

            <?php if ($mode === 'reset'): ?>
                <label>
                    아이디
                    <input
                        name="username"
                        required
                        autocomplete="username"
                        value="<?=h($_POST['username'] ?? '')?>"
                    >
                </label>
            <?php endif; ?>

            <label>
                이름
                <input
                    name="name"
                    required
                    value="<?=h($_POST['name'] ?? '')?>"
                >
            </label>

            <label>
                휴대폰 번호
                <input
                    name="phone"
                    required
                    inputmode="numeric"
                    placeholder="01012345678"
                    value="<?=h($_POST['phone'] ?? '')?>"
                >
            </label>

            <button class="btn recovery-submit" type="submit">
                <?=$mode === 'find'
                    ? '아이디 확인'
                    : '알림톡으로 임시 비밀번호 받기'?>
            </button>
        </form>

        <div class="login-help single">
            <a class="login-help-link" href="login.php">
                관리자 로그인으로 돌아가기
            </a>
        </div>
    </div>
</div>
</body>
</html>
