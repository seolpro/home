<?php
/**
 * 관리자 비밀번호 초기화
 * 실행 후 반드시 삭제하세요.
 */

require_once __DIR__ . '/lib.php';

$newId = 'admin';
$newPassword = 'aj****30';   // ← 원하는 비밀번호로 변경 가능

try {

    $configFile = __DIR__ . '/config.php';

    if (!file_exists($configFile)) {
        exit('config.php를 찾을 수 없습니다.');
    }

    $config = include $configFile;

    if (!isset($config['admin'])) {
        exit('admin 설정을 찾을 수 없습니다.');
    }

    $config['admin']['id'] = $newId;
    $config['admin']['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);

    $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";

    file_put_contents($configFile, $content);

    echo "<h2>관리자 계정이 초기화되었습니다.</h2>";
    echo "<hr>";
    echo "<b>아이디 :</b> {$newId}<br>";
    echo "<b>비밀번호 :</b> {$newPassword}<br><br>";
    echo "<font color='red'><b>반드시 reset_admin_password.php 파일을 삭제하세요.</b></font>";

} catch (Throwable $e) {

    echo "오류 : " . $e->getMessage();

}