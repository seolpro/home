<?php
require_once dirname(__DIR__) . '/lib.php';
admin_required();

$flash = (string)($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$c = $stmt->fetch();

if (!$c) {
    die('계약서 없음');
}

$lessorToken = (string)($_SESSION['new_lessor_token'] ?? '');
$lesseeToken = (string)($_SESSION['new_lessee_token'] ?? '');
$baseUrl = rtrim((string)cfg('base_url'), '/');

$lessorUrl = $lessorToken !== ''
    ? $baseUrl . '/sign.php?party=lessor&token=' . urlencode($lessorToken)
    : '';

$lesseeUrl = $lesseeToken !== ''
    ? $baseUrl . '/sign.php?party=lessee&token=' . urlencode($lesseeToken)
    : '';

$isSuccess = $flash !== '' && (
    strpos($flash, '발송했습니다') !== false ||
    strpos($flash, '성공') !== false
);
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../assets/app.css">
    <title>계약서</title>
</head>
<body>
<?php include '_top.php'; ?>

<main class="wrap">
    <?php if ($flash !== ''): ?>
        <div class="alert <?= $isSuccess ? 'success' : 'warn' ?>" style="margin-bottom:16px;">
            <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <div class="card no-print">
        <h2>진행 상태: <?= e($c['status']) ?></h2>

        <?php if ($lessorUrl !== ''): ?>
            <label>
                임대인 서명 링크
                <input readonly value="<?= e($lessorUrl) ?>" onclick="this.select()">
            </label>
        <?php endif; ?>

        <?php if ($lesseeUrl !== ''): ?>
            <label>
                임차인 서명 링크
                <input readonly value="<?= e($lesseeUrl) ?>" onclick="this.select()">
            </label>
        <?php endif; ?>

        <div class="actions">
            <form method="post" action="send_link.php">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="party" value="lessor">
                <button type="submit" class="btn">임대인에게 링크 문자</button>
            </form>

            <?php if (!empty($c['lessor_signed_at'])): ?>
                <form method="post" action="send_link.php">
                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="party" value="lessee">
                    <button type="submit" class="btn green">임차인에게 링크 문자</button>
                </form>
            <?php endif; ?>

            <?php if ($c['status'] === 'completed'): ?>
                <a class="btn green" href="final.php?id=<?= $id ?>">최종 PDF 생성·발송</a>
            <?php endif; ?>
        </div>

        <p class="small">
            보안을 위해 링크 원문은 최초 생성 직후에만 화면에 표시됩니다.
            링크가 없으면 아래 재발급 버튼을 사용하세요.
        </p>

        <form method="post" action="regenerate.php">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn gray">서명 링크 모두 재발급</button>
        </form>
    </div>

    <?= render_contract($c) ?>
</main>
</body>
</html>
