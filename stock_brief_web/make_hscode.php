<?php
/**
 * 관리자 비밀번호 해시 생성기
 * 사용 후 서버에서 반드시 삭제하세요.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$password = (string)($_POST['password'] ?? '');
$hash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $password !== '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
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
    <title>관리자 비밀번호 해시 생성기</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #1f2937;
            font-family: Arial, "Noto Sans KR", sans-serif;
        }

        .wrap {
            width: min(720px, calc(100% - 28px));
            margin: 50px auto;
        }

        .card {
            background: #fff;
            border: 1px solid #e3e8ef;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
        }

        h1 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid #cfd6df;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 16px;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        button {
            margin-top: 14px;
            border: 0;
            border-radius: 10px;
            padding: 12px 18px;
            background: #16803b;
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .result {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .notice {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fff4e5;
            border: 1px solid #ffd8a8;
        }

        code {
            word-break: break-all;
        }
    </style>
</head>

<body>
<main class="wrap">
    <section class="card">

        <h1>🔐 관리자 비밀번호 해시 생성기</h1>

        <form method="post">
            <label for="password">
                새 관리자 비밀번호
            </label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
                required
            >

            <button type="submit">
                해시 생성
            </button>
        </form>

        <?php if ($hash !== ''): ?>

            <div class="result">

                <label>
                    생성된 password_hash
                </label>

                <textarea
                    readonly
                    onclick="this.select()"
                ><?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?></textarea>

                <br><br>

                <label>
                    config.php에 넣을 코드
                </label>

                <textarea
                    readonly
                    onclick="this.select()"
                >'admin' => [
    'id' => 'admin',
    'password_hash' => '<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>',
],</textarea>

            </div>

        <?php endif; ?>

        <div class="notice">
            ⚠️ 해시를 생성하고 config.php에 적용한 뒤
            이 파일은 서버에서 반드시 삭제하세요.
        </div>

    </section>
</main>
</body>
</html>