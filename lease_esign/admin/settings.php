<?php

require_once dirname(__DIR__) . '/lib.php';

admin_required();

$msg = '';
$msgType = 'warn';

$configFile = dirname(__DIR__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        if (!is_file($configFile)) {
            throw new RuntimeException('config.php 파일을 찾을 수 없습니다.');
        }

        /*
         * cfg()의 정적 캐시값을 사용하지 않고
         * 실제 config.php를 다시 읽습니다.
         */
        $config = require $configFile;

        if (!is_array($config)) {
            throw new RuntimeException('config.php 설정 형식이 올바르지 않습니다.');
        }

        /*
         * 기존 관리자 설정 보존
         */
        $existingAdmin = $config['admin'] ?? [];

        if (!is_array($existingAdmin)) {
            $existingAdmin = [];
        }

        $adminId = trim((string)($existingAdmin['id'] ?? 'admin'));
        $adminHash = trim((string)($existingAdmin['password_hash'] ?? ''));

        /*
         * 관리자 해시가 비어 있으면 설정 저장을 중단합니다.
         * 문자 설정 저장 때문에 비밀번호가 삭제되는 것을 방지합니다.
         */
        if ($adminHash === '') {
            throw new RuntimeException(
                '관리자 비밀번호 해시가 비어 있습니다. '
                . '먼저 config.php의 admin.password_hash를 설정해 주세요.'
            );
        }

        /*
         * 문자 설정만 변경
         */
        if (!isset($config['sms']) || !is_array($config['sms'])) {
            $config['sms'] = [];
        }

        $config['sms']['enabled'] = isset($_POST['enabled']);
        $config['sms']['provider'] = 'ppurio';
        $config['sms']['account'] = trim((string)($_POST['account'] ?? ''));
        $config['sms']['sender'] = preg_replace(
            '/[^0-9]/',
            '',
            (string)($_POST['sender'] ?? '')
        );
        $config['sms']['admin_phone'] = preg_replace(
            '/[^0-9]/',
            '',
            (string)($_POST['admin_phone'] ?? '')
        );

        /*
         * 인증키 입력란을 비워서 저장하면
         * 기존 인증키를 유지합니다.
         */
        $inputAuthKey = trim((string)($_POST['auth_key'] ?? ''));

        if ($inputAuthKey !== '') {
            $config['sms']['auth_key'] = $inputAuthKey;
        } elseif (!array_key_exists('auth_key', $config['sms'])) {
            $config['sms']['auth_key'] = '';
        }

        /*
         * 관리자 설정을 원래 값으로 다시 확실하게 보존
         */
        $config['admin'] = [
            'id' => $adminId !== '' ? $adminId : 'admin',
            'password_hash' => $adminHash,
        ];

        $php =
            "<?php\n\n"
            . "return "
            . var_export($config, true)
            . ";\n";

        /*
         * 임시 파일에 먼저 저장한 후 교체합니다.
         * 저장 도중 config.php가 깨지는 것을 방지합니다.
         */
        $tempFile = $configFile . '.tmp';

        $written = file_put_contents(
            $tempFile,
            $php,
            LOCK_EX
        );

        if ($written === false) {
            throw new RuntimeException('임시 설정 파일 저장에 실패했습니다.');
        }

        /*
         * 저장된 PHP 설정이 정상인지 확인
         */
        $savedConfig = require $tempFile;

        if (
            !is_array($savedConfig)
            || empty($savedConfig['admin']['password_hash'])
        ) {
            @unlink($tempFile);

            throw new RuntimeException(
                '설정 검증에 실패하여 기존 config.php를 유지했습니다.'
            );
        }

        /*
         * 기존 설정 백업
         */
        $backupFile = $configFile . '.backup';

        if (is_file($configFile)) {
            @copy($configFile, $backupFile);
        }

        /*
         * 임시 파일을 실제 config.php로 교체
         */
        if (!rename($tempFile, $configFile)) {
            @unlink($tempFile);

            throw new RuntimeException('config.php 교체에 실패했습니다.');
        }

        $msg = '문자 설정을 저장했습니다.';
        $msgType = 'ok';

    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $msgType = 'warn';
    }
}

/*
 * 저장 이후 실제 설정 파일을 다시 읽습니다.
 */
$config = is_file($configFile)
    ? require $configFile
    : [];

$sms = $config['sms'] ?? [];

if (!is_array($sms)) {
    $sms = [];
}

$sms = array_merge([
    'enabled' => false,
    'provider' => 'ppurio',
    'account' => '',
    'auth_key' => '',
    'sender' => '',
    'admin_phone' => '',
], $sms);

?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        rel="stylesheet"
        href="../assets/app.css"
    >

    <title>문자 설정</title>
</head>

<body>

<?php include '_top.php'; ?>

<main class="narrow">

    <h1>뿌리오 문자 설정</h1>

    <?php if ($msg !== ''): ?>
        <div class="alert <?=e($msgType)?>">
            <?=e($msg)?>
        </div>
    <?php endif; ?>

    <form method="post" class="card">

        <input
            type="hidden"
            name="csrf"
            value="<?=e(csrf())?>"
        >

        <label class="checkline">
            <input
                type="checkbox"
                name="enabled"
                value="1"
                <?=!empty($sms['enabled']) ? 'checked' : ''?>
            >
            문자 발송 사용
        </label>

        <label>
            계정

            <input
                name="account"
                value="<?=e($sms['account'])?>"
                autocomplete="off"
            >
        </label>

        <label>
            인증키

            <input
                type="password"
                name="auth_key"
                value=""
                placeholder="변경할 때만 입력"
                autocomplete="new-password"
            >
        </label>

        <?php if (!empty($sms['auth_key'])): ?>
            <div class="small">
                현재 인증키가 저장되어 있습니다.
                변경하지 않으려면 입력하지 마세요.
            </div>
        <?php endif; ?>

        <label>
            발신번호

            <input
                name="sender"
                value="<?=e($sms['sender'])?>"
                inputmode="numeric"
                placeholder="01071186639"
            >
        </label>

        <label>
            서명완료 알림번호

            <input
                name="admin_phone"
                value="<?=e($sms['admin_phone'])?>"
                inputmode="numeric"
                placeholder="01071186639"
            >
        </label>

        <br>

        <button class="btn big" type="submit">
            저장
        </button>

    </form>

    <div class="alert warn">
        임차인 서명 완료 시 임대인 연락처와 위 알림번호로 안내합니다.
        최종 PDF 생성 후에는 임차인에게 PDF 다운로드 링크를 발송합니다.
        발신번호는 뿌리오에 사전 등록된 번호를 입력하세요.
    </div>

</main>

</body>
</html>