<?php
declare(strict_types=1);
// One-time browser-based password hash generator. Remove after use.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$lock = __DIR__ . '/.make_hash_web_used.lock';
$locked = file_exists($lock);
$error = '';
$hash = '';
if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[0-9]{4}$/D', $password)) {
        $error = '비밀번호는 숫자 4자리로 입력해 주세요.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            $error = '암호화에 실패했습니다.';
        } else {
            // Atomic creation prevents another request from generating a hash.
            $handle = @fopen($lock, 'x');
            if ($handle === false) {
                $hash = '';
                $error = '이미 사용되었거나 잠금 파일을 생성할 수 없습니다. 서버 폴더 쓰기 권한을 확인해 주세요.';
            } else {
                fwrite($handle, 'Used at ' . date('c') . "\n");
                fclose($handle);
                @chmod($lock, 0600);
            }
        }
    }
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>관리자 비밀번호 해시 생성</title><style>body{margin:0;background:#f4f6fa;font-family:system-ui,'Malgun Gothic',sans-serif;color:#202735}.card{max-width:580px;margin:9vh auto;padding:28px;background:#fff;border-radius:18px;box-shadow:0 12px 40px #16253a16}h1{font-size:23px}label{display:block;margin:20px 0 8px;font-weight:700}input{box-sizing:border-box;width:100%;padding:13px;border:1px solid #b7c1d1;border-radius:9px;font-size:16px}button{padding:12px 18px;border:0;border-radius:9px;background:#2563eb;color:white;font-weight:700;cursor:pointer}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#edf2fa;padding:16px;border-radius:9px}.warn{color:#a34116}.muted{color:#59677b;font-size:14px;line-height:1.65}</style></head><body><main class="card"><h1>🔐 관리자 비밀번호 해시 생성</h1><p class="muted">사과농장 주문관리 · 최초 1회 생성용</p>
<?php if ($hash !== ''): ?><p><strong>생성 완료</strong> — 이 화면을 닫기 전에 아래 해시값을 복사하세요.</p><pre id="hash"><?=h($hash)?></pre><button type="button" onclick="navigator.clipboard.writeText(document.getElementById('hash').textContent).then(()=>alert('복사했습니다.')).catch(()=>alert('직접 선택하여 복사해 주세요.'))">해시값 복사</button><p class="muted">config.php의 <code>admin_password_hash</code> 값에 붙여넣으세요. 로그인에는 입력했던 원래 비밀번호를 사용합니다.</p>
<?php elseif ($locked): ?><p class="warn">이미 한 번 사용한 페이지입니다. 재사용이 차단되었습니다.</p><p class="muted">해시를 복사하지 못했다면 서버에서 <code>.make_hash_web_used.lock</code>을 삭제한 뒤 다시 실행할 수 있습니다. 재실행 후 즉시 도구 파일을 삭제하세요.</p>
<?php else: ?><?php if ($error !== ''): ?><p class="warn"><?=h($error)?></p><?php endif; ?><form method="post" autocomplete="off"><label for="password">새 관리자 비밀번호 (숫자 4자리)</label><input id="password" name="password" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="new-password" required><p><button type="submit">비밀번호 해시 생성 (1회)</button></p></form><?php endif; ?><p class="warn"><strong>중요:</strong> 생성 후 <code>make_hash_web.php</code>와 잠금파일을 서버에서 삭제하세요. 공개된 URL을 다른 사람이 먼저 실행할 위험이 있으므로 업로드 후 즉시 사용하세요.</p></main></body></html>
