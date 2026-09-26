<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

const WEB_MAX_WIDTH = 1600;
const WEB_JPEG_QUALITY = 78;

function eh(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function safe_upload_file(string $file): ?array {
    $file = ltrim(str_replace('\\', '/', $file), '/');
    if ($file === '' || strpos($file, '..') !== false || strpos($file, 'uploads/') !== 0) return null;
    if (strpos($file, 'uploads/web/') === 0) return null;
    $rel = substr($file, strlen('uploads/'));
    return [$file, __DIR__ . '/uploads/' . $rel, __DIR__ . '/uploads/web/' . $rel];
}

function exif_orientation(string $src): int {
    if (!function_exists('exif_read_data')) return 1;
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    if ($ext !== 'jpg' && $ext !== 'jpeg') return 1;
    $exif = @exif_read_data($src, 'IFD0', true, false);
    if (!is_array($exif)) return 1;
    if (isset($exif['IFD0']['Orientation'])) return (int)$exif['IFD0']['Orientation'];
    if (isset($exif['Orientation'])) return (int)$exif['Orientation'];
    return 1;
}

function orient_image($im, int $orientation) {
    switch ($orientation) {
        case 2: if (function_exists('imageflip')) imageflip($im, IMG_FLIP_HORIZONTAL); break;
        case 3: $im = imagerotate($im, 180, 0); break;
        case 4: if (function_exists('imageflip')) imageflip($im, IMG_FLIP_VERTICAL); break;
        case 5:
            if (function_exists('imageflip')) imageflip($im, IMG_FLIP_HORIZONTAL);
            $im = imagerotate($im, -90, 0);
            break;
        case 6: $im = imagerotate($im, -90, 0); break;
        case 7:
            if (function_exists('imageflip')) imageflip($im, IMG_FLIP_HORIZONTAL);
            $im = imagerotate($im, 90, 0);
            break;
        case 8: $im = imagerotate($im, 90, 0); break;
    }
    return $im;
}

function make_one(string $src, string $dst): array {
    if (!extension_loaded('gd')) return [false, 'PHP GD 확장이 없습니다.'];

    $info = @getimagesize($src);
    if (!$info) return [false, '이미지 정보를 읽을 수 없습니다.'];

    $type = $info[2];
    if ($type === IMAGETYPE_JPEG) $im = @imagecreatefromjpeg($src);
    elseif ($type === IMAGETYPE_PNG) $im = @imagecreatefrompng($src);
    elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) $im = @imagecreatefromwebp($src);
    else return [false, 'JPG/PNG/WebP만 지원합니다.'];

    if (!$im) return [false, '원본 이미지를 열 수 없습니다.'];

    $orientation = ($type === IMAGETYPE_JPEG) ? exif_orientation($src) : 1;
    $im = orient_image($im, $orientation);

    $w = imagesx($im);
    $h = imagesy($im);
    $nw = min(WEB_MAX_WIDTH, $w);
    $nh = max(1, (int)round($h * ($nw / $w)));

    $canvas = imagecreatetruecolor($nw, $nh);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopyresampled($canvas, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $dir = dirname($dst);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($im); imagedestroy($canvas);
        return [false, 'uploads/web 폴더 생성 실패'];
    }

    $ext = strtolower(pathinfo($dst, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') $saved = @imagejpeg($canvas, $dst, WEB_JPEG_QUALITY);
    elseif ($ext === 'png') $saved = @imagepng($canvas, $dst, 7);
    elseif ($ext === 'webp' && function_exists('imagewebp')) $saved = @imagewebp($canvas, $dst, 78);
    else $saved = false;

    imagedestroy($im);
    imagedestroy($canvas);

    if (!$saved) return [false, '웹용 이미지 저장 실패'];

    clearstatcache(true, $dst);
    $before = @filesize($src) ?: 0;
    $after = @filesize($dst) ?: 0;
    return [true, 'EXIF 방향 ' . $orientation . ' 반영 · ' .
        number_format($before/1048576,2) . 'MB → ' . number_format($after/1048576,2) . 'MB'];
}

$data = load_data();
$items = $data['items'] ?? [];
$files = [];

foreach ($items as $it) {
    $safe = safe_upload_file((string)($it['file'] ?? ''));
    if ($safe) $files[$safe[0]] = $safe;
}
$files = array_values($files);
$total = count($files);

$i = isset($_GET['i']) ? max(0, (int)$_GET['i']) : 0;
$run = isset($_GET['run']) && $_GET['run'] === '1';
$current = '';
$msg = '';
$ok = null;

if ($run && $total > 0 && $i < $total) {
    [$current, $src, $dst] = $files[$i];
    if (!is_file($src)) {
        $ok = false; $msg = '원본 파일 없음';
    } else {
        // 중요: 기존 web 파일이 있어도 EXIF를 바로잡기 위해 무조건 재생성
        [$ok, $msg] = make_one($src, $dst);
    }
}

$next = $i + 1;
$done = ($total === 0) || ($run && $next >= $total);
$processed = $run ? min($next, $total) : 0;
$pct = $total ? (int)round(($processed/$total)*100) : 0;
?>
<!doctype html>
<html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>웹용 사진 방향 바로잡기</title>
<style>
body{margin:0;background:#f4f6f9;color:#172033;font-family:system-ui,-apple-system,"Noto Sans KR",sans-serif}.wrap{max-width:760px;margin:30px auto;padding:16px}.card{background:#fff;border:1px solid #dde3ec;border-radius:16px;padding:22px;box-shadow:0 7px 25px #0001}h1{margin:0 0 8px}.muted{color:#657083;line-height:1.6}.notice{padding:13px;background:#eef5ff;border-left:4px solid #1f5eff;border-radius:8px;margin:15px 0}.result{padding:13px;border-radius:9px;margin:15px 0;word-break:break-all}.ok{background:#ecfdf3;color:#067647}.bad{background:#fef3f2;color:#b42318}.progress{height:13px;background:#e8edf3;border-radius:999px;overflow:hidden;margin:15px 0}.bar{height:100%;background:#1f5eff}.btn{display:inline-block;border:0;border-radius:10px;padding:12px 16px;background:#172033;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.btn.alt{background:#eef2f7;color:#172033}.actions{display:flex;gap:8px;flex-wrap:wrap}.small{font-size:13px;color:#657083}
</style></head><body><main class="wrap"><section class="card">
<h1>사진 방향 바로잡기</h1>
<p class="muted">스마트폰 원본의 EXIF Orientation을 읽어 웹용 사진을 올바른 방향으로 다시 생성합니다.</p>
<div class="notice"><b>원본사진은 변경하지 않습니다.</b><br>기존 <code>uploads/web/</code> 이미지만 올바른 방향으로 다시 만듭니다.</div>

<?php if ($total === 0): ?>
<div class="result bad">등록된 원본사진을 찾지 못했습니다.</div>
<?php else: ?>
<div><b>전체 <?=number_format($total)?>장</b> · <?=number_format($processed)?>장 처리</div>
<div class="progress"><div class="bar" style="width:<?=$pct?>%"></div></div>

<?php if ($run): ?>
<div class="result <?=$ok?'ok':'bad'?>"><b><?=$ok?'재생성 완료':'처리 실패'?></b><br><?=eh($current)?><br><?=eh($msg)?></div>
<?php endif; ?>

<div class="actions">
<?php if (!$run): ?>
<a class="btn" href="?run=1&i=0">첫 사진 방향 바로잡기</a>
<?php elseif (!$done): ?>
<a class="btn" href="?run=1&i=<?=$next?>">다음 사진 1장</a>
<a class="btn alt" id="autoBtn" href="?run=1&i=<?=$next?>">자동으로 계속 처리</a>
<?php else: ?>
<a class="btn" href="index.php">완료 · 증거자료 페이지 확인</a>
<?php endif; ?>
</div>
<p class="small">처리 후 브라우저가 이전 이미지를 캐시하고 있으면 Ctrl+F5 또는 강력 새로고침을 한 번 해주세요.</p>
<?php endif; ?>
</section></main>
<?php if($run && !$done): ?>
<script>
document.getElementById('autoBtn')?.addEventListener('click',function(e){
 e.preventDefault(); const u=this.href; this.textContent='다음 사진 처리 중…'; setTimeout(()=>location.href=u,450);
});
</script>
<?php endif; ?>
</body></html>
