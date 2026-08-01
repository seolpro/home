<?php
if (!isset($pageTitle)) {
    $pageTitle = '관리';
}
?>
<header class="top">
    <a href="index.php"><b><?=e(cfg('app_name'))?></b></a>
    <nav>
        <a href="index.php?view=progress">진행 계약</a>　
        <a href="index.php?view=completed">완료 계약</a>　
        <a href="index.php">전체 계약</a>　
        <a href="create.php">새 계약</a>　
        <a href="settings.php">문자설정</a>　
        <a href="logout.php">로그아웃</a>
    </nav>
</header>
