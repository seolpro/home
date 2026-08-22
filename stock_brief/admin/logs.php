<?php
require_once dirname(__DIR__) . '/lib.php';
admin_required();

$rows = db()->query(
    "SELECT * FROM stock_sms_logs ORDER BY id DESC LIMIT 200"
)->fetchAll();
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../assets/app.css">
    <title>발송로그</title>
</head>
<body>
<?php include '_top.php'; ?>

<main class="wrap">
    <h1>📨 주식 브리핑 문자 발송로그</h1>

    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>발송일시</th>
                    <th>수신번호</th>
                    <th>내용</th>
                    <th>결과</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $result = json_decode(
                        (string)($r['result_json'] ?? ''),
                        true
                    );
                    ?>
                    <tr>
                        <td><?= e($r['created_at']) ?></td>
                        <td><?= e($r['recipient']) ?></td>
                        <td style="white-space:pre-wrap;min-width:420px"><?= e($r['message']) ?></td>
                        <td>
                            <?= !empty($result['ok'])
                                ? '✅ 성공'
                                : '❌ 실패' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="4">
                            아직 주식 브리핑 발송내역이 없습니다.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
