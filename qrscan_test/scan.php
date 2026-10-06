<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$pdo = db();

$message = '';
$messageType = '';
$memberNo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $raw = trim((string)($_POST['qr_data'] ?? ''));

    if ($raw === '') {

        $message = 'QR 데이터가 입력되지 않았습니다.';
        $messageType = 'danger';

    } else {

        /*
         * QR 예:
         * AT-5c237a179d7ad28f-4795888-98de4d5bcb1732ab
         *
         * 스캐너가 소문자로 출력해도 허용
         */

        if (
            preg_match(
                '/^at-[a-f0-9]+-([0-9]{7,8})-[a-f0-9]+$/i',
                $raw,
                $matches
            )
        ) {

            $memberNo = $matches[1];

            try {

                /*
                 * 참석자 등록
                 *
                 * qr_attendance 테이블:
                 * member_no
                 * checked_at
                 */

                $stmt = $pdo->prepare("
    INSERT INTO qr_test_attendance
        (member_no, scanned_code, checked_at)
    VALUES
        (?, ?, NOW())
");

$stmt->execute([
    $memberNo,
    $raw
]);

                $message =
                    '참석 등록 완료 - 조합원번호 ' .
                    $memberNo;

                $messageType = 'success';

            } catch (PDOException $e) {

                /*
                 * 중복 등록
                 */
                if (
                    $e->getCode() === '23000'
                    || str_contains(
                        strtolower($e->getMessage()),
                        'duplicate'
                    )
                ) {

                    $message =
                        '이미 참석 등록된 조합원입니다. - ' .
                        $memberNo;

                    $messageType = 'warning';

                } else {

                    $message =
                        'DB 저장 오류: ' .
                        $e->getMessage();

                    $messageType = 'danger';
                }
            }

        } else {

            $message =
                '올바른 참석 QR이 아닙니다. 입력값: ' .
                $raw;

            $messageType = 'danger';
        }
    }
}


/*
 * 최근 참석자
 */

$recent = [];

try {

    $recent = $pdo->query("
        SELECT
            member_no,
            checked_at
        FROM qr_test_attendance
        ORDER BY id DESC
        LIMIT 10
    ")->fetchAll();

} catch (Throwable $e) {
    // 테스트 화면이므로 목록 오류는 무시
}

?>
<!doctype html>
<html lang="ko">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>QR 참석체크 테스트</title>

<style>

body {
    margin:0;
    background:#f4f6f9;
    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
}

.container {
    max-width:720px;
    margin:50px auto;
    padding:20px;
}

.card {
    background:#fff;
    border-radius:18px;
    padding:30px;
    box-shadow:
        0 5px 25px rgba(0,0,0,.08);
}

h1 {
    margin-top:0;
}

.scan-input {
    width:100%;
    box-sizing:border-box;
    font-size:22px;
    padding:18px;
    border:3px solid #0d6efd;
    border-radius:12px;
    outline:none;
}

.help {
    margin-top:12px;
    color:#666;
}

.alert {
    margin-bottom:25px;
    padding:20px;
    border-radius:12px;
    font-size:21px;
    font-weight:700;
}

.success {
    background:#d1e7dd;
    color:#0f5132;
}

.warning {
    background:#fff3cd;
    color:#664d03;
}

.danger {
    background:#f8d7da;
    color:#842029;
}

table {
    width:100%;
    border-collapse:collapse;
    margin-top:30px;
}

th,
td {
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:left;
}

.status {
    display:inline-block;
    background:#198754;
    color:#fff;
    padding:5px 10px;
    border-radius:20px;
    font-size:13px;
}

</style>

</head>

<body>

<div class="container">

<div class="card">

<h1>QR 참석체크</h1>

<p>
USB QR 스캐너로 조합원의 QR 참석증을
스캔해 주세요.
</p>


<?php if ($message !== ''): ?>

<div class="alert <?=h($messageType)?>">
    <?=h($message)?>
</div>

<?php endif; ?>


<form
    method="post"
    id="scanForm"
    autocomplete="off"
>

<input
    type="text"
    name="qr_data"
    id="qr_data"
    class="scan-input"
    placeholder="QR 스캔 대기중..."
    autofocus
>

</form>


<div class="help">

QR을 읽으면 자동으로 참석 처리됩니다.<br>
마우스로 입력창을 클릭할 필요가 없도록
자동 포커스됩니다.

</div>


<?php if ($recent): ?>

<h2>최근 참석자</h2>

<table>

<thead>

<tr>
    <th>조합원번호</th>
    <th>참석시간</th>
</tr>

</thead>

<tbody>

<?php foreach ($recent as $row): ?>

<tr>

<td>
    <strong><?=h($row['member_no'])?></strong>
</td>

<td>
    <?=h($row['checked_at'])?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<?php endif; ?>


</div>

</div>


<script>

/*
 * 페이지가 열리거나
 * 참석처리 후 다시 표시될 때
 * 항상 QR 입력창에 포커스
 */

const input = document.getElementById('qr_data');

function focusScanner() {

    input.focus();

}

window.addEventListener(
    'load',
    function () {

        focusScanner();

    }
);


/*
 * 화면 다른 곳을 눌렀더라도
 * 다시 입력창으로 포커스
 */

document.addEventListener(
    'click',
    function () {

        focusScanner();

    }
);


/*
 * Enter 수신
 */

input.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Enter') {

            event.preventDefault();

            const value =
                input.value.trim();

            if (value !== '') {

                document
                    .getElementById('scanForm')
                    .submit();

            }

        }

    }
);

</script>

</body>
</html>