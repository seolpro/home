<?php
require_once dirname(__DIR__) . '/lib.php';
admin_required();

$view = (string)($_GET['view'] ?? 'all');

if ($view === 'progress') {
    $pageTitle = '진행 계약';
    $stmt = db()->prepare("SELECT * FROM contracts WHERE status <> 'completed' ORDER BY id DESC");
    $stmt->execute();
    $rows = $stmt->fetchAll();
} elseif ($view === 'completed') {
    $pageTitle = '완료 계약';
    $stmt = db()->prepare("SELECT * FROM contracts WHERE status = 'completed' ORDER BY id DESC");
    $stmt->execute();
    $rows = $stmt->fetchAll();
} else {
    $view = 'all';
    $pageTitle = '전체 계약';
    $rows = db()->query('SELECT * FROM contracts ORDER BY id DESC')->fetchAll();
}

$statusLabels = [
    'draft' => '작성·서명 대기',
    'lessor_signed' => '임대인 서명 완료',
    'completed' => '계약 서명 완료',
];
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../assets/app.css">
    <title><?=e($pageTitle)?></title>
</head>
<body>
<?php include '_top.php'; ?>
<main class="wrap">
    <div class="actions">
        <a class="btn green" href="create.php">＋ 새 계약서 만들기</a>
        <a class="btn <?=$view === 'progress' ? 'green' : 'gray'?>" href="index.php?view=progress">⚙️진행 계약</a>
        <a class="btn <?=$view === 'completed' ? 'green' : 'gray'?>" href="index.php?view=completed">✅완료 계약</a>
        <a class="btn <?=$view === 'all' ? 'green' : 'gray'?>" href="index.php">📚전체 계약</a>
    </div>

    <h1><?=e($pageTitle)?> 🧾목록</h1>

    <div class="table-wrap">
        <table class="list">
            <thead>
            <tr>
                <th>번호</th>
                <th>부동산</th>
                <th>임대인 / 임차인</th>
                <th>상태</th>
                <th>작성일</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="6">표시할 계약서가 없습니다.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $x): ?>
                    <tr>
                        <td><?=$x['id']?></td>
                        <td><?=e($x['property_address'])?></td>
                        <td><?=e($x['lessor_name'])?> / <?=e($x['lessee_name'])?></td>
                        <td>
                            <span class="status">
                                <?=e($statusLabels[$x['status']] ?? $x['status'])?>
                            </span>
                        </td>
                        <td><?=e($x['created_at'])?></td>
                        <td><a class="btn" href="view.php?id=<?=$x['id']?>">열기</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
