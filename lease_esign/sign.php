<?php

require_once __DIR__ . '/lib.php';

$party = (($_GET['party'] ?? '') === 'lessor')
    ? 'lessor'
    : 'lessee';

$t = (string)($_GET['token'] ?? '');

try {
    $c = contract_by_token($t, $party);
} catch (Throwable $e) {
    $err = $e->getMessage();
}

$name = $party === 'lessor'
    ? ($c['lessor_name'] ?? '')
    : ($c['lessee_name'] ?? '');

$signed = $party === 'lessor'
    ? ($c['lessor_signed_at'] ?? null)
    : ($c['lessee_signed_at'] ?? null);

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
        href="assets/app.css"
    >

    <link
        rel="stylesheet"
        href="assets/contract-reading-highlight.css?v=20260814_2"
    >

    <title>계약 확인 및 서명</title>
</head>

<body>

<main class="wrap">

    <?php if (isset($err)): ?>

        <div class="alert err">
            <h1><?= e($err) ?></h1>
        </div>

    <?php else: ?>

        <div class="alert warn">
            <b><?= e($name) ?>님</b>,
            아래 계약 내용을 천천히 확인해 주세요.
            글자가 작으면 휴대전화 화면을 두 손가락으로 확대할 수 있습니다.
        </div>

        <div id="contractDocument">
            <?= render_contract($c) ?>
        </div>

        <section class="card no-print">

            <h1>
                <?= $party === 'lessor'
                    ? '임대인'
                    : '임차인' ?>
                전자서명
            </h1>

            <?php if ($signed): ?>

                <div class="alert ok">
                    이미 <?= e($signed) ?>에 서명이 완료되었습니다.
                </div>

            <?php else: ?>

                <label>
                    본인 확인: 휴대전화 번호 뒤 4자리

                    <input
                        id="phone4"
                        inputmode="numeric"
                        maxlength="4"
                        placeholder="예: 1234"
                    >
                </label>

                <div class="checkline">
                    <label>
                        <input
                            type="checkbox"
                            id="agree1"
                        >
                        위 계약서 전체 내용을 읽고 이해했습니다.
                    </label>
                </div>

                <div class="checkline">
                    <label>
                        <input
                            type="checkbox"
                            id="agree2"
                        >
                        전자문서와 전자서명 방식으로 계약하는 데 동의합니다.
                    </label>
                </div>

                <p>
                    <b>
                        아래 흰색 칸에 손가락 또는 마우스로 서명하세요.
                    </b>
                </p>

                <canvas
                    id="pad"
                    class="sigpad"
                ></canvas>

                <div class="actions">

                    <button
                        type="button"
                        class="btn gray"
                        id="clear"
                    >
                        서명 다시 쓰기
                    </button>

                    <button
                        type="button"
                        class="btn big green"
                        id="submit"
                    >
                        확인하고 서명 완료
                    </button>

                </div>

                <p
                    id="msg"
                    class="alert"
                    style="display:none"
                ></p>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

<?php if (!isset($err) && !$signed): ?>

    <script src="assets/sign.js"></script>

    <script>
        LeaseSign.init({
            token: <?= json_encode($t) ?>,
            party: <?= json_encode($party) ?>
        });
    </script>

<?php endif; ?>

<script src="assets/contract-reading-highlight.js?v=20260814_2"></script>

</body>
</html>
