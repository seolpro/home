<?php
/**
 * 관리자 비밀번호 해시 생성기
 * 실행 후 반드시 삭제하세요.
 */

header('Content-Type: text/html; charset=utf-8');

$password = $_POST['password'] ?? '';

?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>Password Hash Generator</title>
<style>
body{
    font-family:Arial;
    max-width:700px;
    margin:40px auto;
    line-height:1.6;
}
input{
    width:100%;
    padding:12px;
    font-size:18px;
}
button{
    padding:12px 25px;
    font-size:18px;
    cursor:pointer;
}
textarea{
    width:100%;
    height:120px;
    font-size:15px;
}
.result{
    background:#f7f7f7;
    padding:15px;
    border-radius:8px;
}
</style>
</head>
<body>

<h2>관리자 비밀번호 해시 생성기</h2>

<form method="post">

    <label>새 비밀번호</label>

    <input
        type="text"
        name="password"
        required
        value="<?=htmlspecialchars($password)?>"
    >

    <br><br>

    <button type="submit">
        해시 생성
    </button>

</form>

<?php
if($password!=''){

    $hash=password_hash($password,PASSWORD_DEFAULT);

    echo "<hr>";

    echo "<div class='result'>";

    echo "<b>비밀번호</b><br>";
    echo htmlspecialchars($password);

    echo "<br><br>";

    echo "<b>password_hash</b><br>";

    echo "<textarea readonly>".$hash."</textarea>";

    echo "<br>";

    echo "<b>config.php에 넣을 코드</b>";

    echo "<textarea readonly>";
?>
'admin' => [
    'id' => 'admin',
    'password_hash' => '<?=htmlspecialchars($hash,ENT_QUOTES)?>',
],
<?php
    echo "</textarea>";

    echo "</div>";
}
?>

<hr>

<font color="red">
생성 후 make_hash.php 파일은 반드시 삭제하세요.
</font>

</body>
</html>