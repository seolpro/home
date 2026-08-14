<?php

require_once dirname(__DIR__) . '/lib.php';

admin_required();

$err = '';

$today = date('Y-m-d');
$endDateDefault = date('Y-m-d', strtotime($today . ' +2 years -1 day'));

function form_value(string $key, string $default = ''): string
{
    return e((string)($_POST[$key] ?? $default));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $lessorToken = token();
        $lesseeToken = token();

        $columns = [
            'property_address',
            'property_detail',
            'floor_info',
            'area_m2',
            'use_type',
            'deposit',
            'monthly_rent',
            'payment_day',
            'rent_account',
            'late_rate',
            'handover_date',
            'start_date',
            'end_date',
            'terms',
            'special_terms',
            'lessor_name',
            'lessor_birth',
            'lessor_phone',
            'lessor_address',
            'agent_name',
            'agent_phone',
            'lessee_name',
            'lessee_birth',
            'lessee_phone',
            'lessee_address',
        ];

        $values = [];
        foreach ($columns as $column) {
            $values[] = $_POST[$column] ?? '';
        }

        $placeholders = rtrim(str_repeat('?,', count($columns)), ',');

        $sql = '
            INSERT INTO contracts (
                ' . implode(',', $columns) . ',
                lessor_token_hash,
                lessee_token_hash,
                token_expires_at,
                created_at,
                updated_at
            )
            VALUES (
                ' . $placeholders . ',
                ?, ?,
                DATE_ADD(NOW(), INTERVAL ? DAY),
                NOW(),
                NOW()
            )
        ';

        $values[] = hash_token($lessorToken);
        $values[] = hash_token($lesseeToken);
        $values[] = (int)cfg('security.token_days');

        $stmt = db()->prepare($sql);
        $stmt->execute($values);

        $id = (int)db()->lastInsertId();

        audit($id, 'admin', 'created');

        $_SESSION['new_lessor_token'] = $lessorToken;
        $_SESSION['new_lessee_token'] = $lesseeToken;

        header('Location: view.php?id=' . $id);
        exit;

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
        content="width=device-width, initial-scale=1"
    >

    <link
        rel="stylesheet"
        href="../assets/app.css"
    >

    <title>새 계약</title>

    <style>
        .create-guide {
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid #dce3ea;
            border-radius: 12px;
            background: #f5f8fb;
            line-height: 1.6;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date-help {
            display: block;
            margin-top: 5px;
            color: #667085;
            font-size: 13px;
            line-height: 1.45;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin: 22px 0 40px;
        }

        @media (max-width: 768px) {
            .form-actions {
                display: block;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<?php include '_top.php'; ?>

<main class="wrap">

    <h1>📑 새 임대차계약서</h1>

    <div class="create-guide">
        계약 내용을 입력한 후 계약서를 생성하세요.
        명도일과 계약 시작일은 <b>오늘 날짜</b>,
        계약 종료일은 <b>2년 후 전날</b>로 자동 입력됩니다.
    </div>

    <?php if ($err !== ''): ?>
        <div class="alert err">
            <?= e($err) ?>
        </div>
    <?php endif; ?>

    <form method="post">

        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrf()) ?>"
        >

        <section class="card grid">

            <h2 class="full section-title">
                🏠 부동산 표시
            </h2>

            <label class="full">
                소재지
                <input
                    name="property_address"
                    value="<?= form_value('property_address', '수원시 팔달구 정조로810번길 7-15') ?>"
                    required
                >
            </label>

            <label>
                상세·지번
                <input
                    name="property_detail"
                    value="<?= form_value('property_detail', '(팔달로1가 7-1번지)') ?>"
                >
            </label>

            <label>
                층수
                <input
                    name="floor_info"
                    value="<?= form_value('floor_info', '3층') ?>"
                >
            </label>

            <label>
                전용면적(㎡)
                <input
                    type="number"
                    step="0.01"
                    name="area_m2"
                    value="<?= form_value('area_m2', '57.7') ?>"
                >
            </label>

            <label>
                용도
                <input
                    name="use_type"
                    value="<?= form_value('use_type', '주거공간') ?>"
                >
            </label>

        </section>

        <section class="card grid">

            <h2 class="full section-title">
                💰 금액 및 계약기간
            </h2>

            <label>
                보증금
                <input
                    type="number"
                    name="deposit"
                    value="<?= form_value('deposit', '5000000') ?>"
                    required
                >
            </label>

            <label>
                월차임
                <input
                    type="number"
                    name="monthly_rent"
                    value="<?= form_value('monthly_rent', '500000') ?>"
                    required
                >
            </label>

            <label>
                매월 납부일
                <input
                    type="number"
                    min="1"
                    max="31"
                    name="payment_day"
                    value="<?= form_value('payment_day', '3') ?>"
                >
            </label>

            <label>
                지연배상 연이율(%)
                <input
                    type="number"
                    step="0.01"
                    name="late_rate"
                    value="<?= form_value('late_rate', '10') ?>"
                >
            </label>

            <label class="full">
                월차임 입금계좌
                <input
                    name="rent_account"
                    value="<?= form_value('rent_account', '신협 010-8298-5938 / 예금주 고정목 (임대인의 배우자)') ?>"
                >
            </label>

            <label>
                명도일
                <input
                    type="date"
                    name="handover_date"
                    value="<?= form_value('handover_date', $today) ?>"
                    required
                >
                <span class="date-help">기본값: 오늘</span>
            </label>

            <label>
                계약 시작일
                <input
                    type="date"
                    name="start_date"
                    value="<?= form_value('start_date', $today) ?>"
                    required
                >
                <span class="date-help">기본값: 오늘</span>
            </label>

            <label>
                계약 종료일
                <input
                    type="date"
                    name="end_date"
                    value="<?= form_value('end_date', $endDateDefault) ?>"
                    required
                >
                <span class="date-help">기본값: 오늘 기준 2년 후 전날</span>
            </label>

        </section>

        <section class="card grid">

            <h2 class="full section-title">
                👥 계약 당사자
            </h2>

            <label>
                임대인 성명
                <input
                    name="lessor_name"
                    value="<?= form_value('lessor_name', '이진형') ?>"
                    required
                >
            </label>

            <label>
                임대인 생년월일
                <input
                    name="lessor_birth"
                    value="<?= form_value('lessor_birth', '430324') ?>"
                >
            </label>

            <label>
                임대인 연락처
                <input
                    name="lessor_phone"
                    value="<?= form_value('lessor_phone') ?>"
                    required
                >
            </label>

            <label class="full">
                임대인 주소
                <input
                    name="lessor_address"
                    value="<?= form_value('lessor_address', '경기도 화성시 동탄대로 시범길 192, 1008동 602호') ?>"
                >
            </label>

            <label>
                대리인(중개인) 성명
                <input
                    name="agent_name"
                    value="<?= form_value('agent_name', '김설호(공인중개사)') ?>"
                >
            </label>

            <label>
                대리인(중개인) 연락처
                <input
                    name="agent_phone"
                    value="<?= form_value('agent_phone', '010-7118-6639') ?>"
                >
            </label>

            <label>
                임차인 성명
                <input
                    name="lessee_name"
                    value="<?= form_value('lessee_name') ?>"
                    required
                >
            </label>

            <label>
                임차인 생년월일
                <input
                    name="lessee_birth"
                    value="<?= form_value('lessee_birth') ?>"
                    placeholder="앞 6자리만 권장"
                >
            </label>

            <label>
                임차인 연락처
                <input
                    name="lessee_phone"
                    value="<?= form_value('lessee_phone') ?>"
                    required
                >
            </label>

            <label class="full">
                임차인 주소
                <input
                    name="lessee_address"
                    value="<?= form_value('lessee_address') ?>"
                >
            </label>

        </section>

        <section class="card">

            <h2 class="section-title">
                📜 기본 계약 조항
            </h2>

            <textarea
                name="terms"
                style="min-height:500px"
            ><?= form_value('terms', default_terms()) ?></textarea>

            <h2 class="section-title">
                📌 추가 특약
            </h2>

            <textarea
                name="special_terms"
                placeholder="추가 특약이 있을 때 입력"
            ><?= form_value('special_terms') ?></textarea>

        </section>

        <div class="form-actions">
            <button
                class="btn big green"
                type="submit"
            >
                📑 계약서 생성
            </button>
        </div>

    </form>

</main>

</body>
</html>
