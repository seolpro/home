<?php

declare(strict_types=1);

function app_config(): array
{
    static $config;
    if ($config !== null) {
        return $config;
    }

    $default = [
        'app_name' => 'BizCard Contact Saver',
        'base_url' => '',
        'ocr_provider' => 'mock',
        'google_vision_api_key' => '',
        'upload_dir' => __DIR__ . '/../storage/uploads',
        'max_upload_mb' => 8,
        'keep_uploaded_files' => true,
    ];

    $configFile = __DIR__ . '/../config.php';
    if (!is_file($configFile)) {
        $config = $default;
        return $config;
    }

    $loaded = require $configFile;
    if (!is_array($loaded)) {
        $loaded = [];
    }

    $config = array_merge($default, $loaded);
    return $config;
}

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function ensure_upload_dir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function allowed_mime_types(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];
}

function save_uploaded_image(array $file): array
{
    $config = app_config();
    $maxBytes = (int)$config['max_upload_mb'] * 1024 * 1024;

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('업로드 중 오류가 발생했습니다.');
    }

    if (($file['size'] ?? 0) <= 0) {
        throw new RuntimeException('업로드된 파일이 비어 있습니다.');
    }

    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('업로드 용량이 너무 큽니다.');
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('올바른 업로드 파일이 아닙니다.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp) ?: '';
    finfo_close($finfo);

    $allowed = allowed_mime_types();
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('지원하지 않는 이미지 형식입니다. JPG, PNG, WEBP 등을 사용해 주세요.');
    }

    $ext = $allowed[$mime];
    $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $uploadDir = (string)$config['upload_dir'];
    ensure_upload_dir($uploadDir);
    $dest = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('업로드 파일을 저장하지 못했습니다.');
    }

    return [
        'path' => $dest,
        'filename' => $safeName,
        'mime' => $mime,
        'size' => (int)$file['size'],
    ];
}

function image_to_base64(string $path): string
{
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        throw new RuntimeException('이미지 파일을 읽지 못했습니다.');
    }
    return base64_encode($bytes);
}

function call_google_vision_ocr(string $imagePath): array
{
    $config = app_config();
    $apiKey = trim((string)($config['google_vision_api_key'] ?? ''));
    if ($apiKey === '') {
        throw new RuntimeException('Google Vision API Key가 설정되지 않았습니다. config.php를 확인해 주세요.');
    }

    $payload = [
        'requests' => [[
            'image' => [
                'content' => image_to_base64($imagePath),
            ],
            'features' => [
                ['type' => 'DOCUMENT_TEXT_DETECTION', 'maxResults' => 1],
            ],
            'imageContext' => [
                'languageHints' => ['ko', 'en'],
            ],
        ]],
    ];

    $ch = curl_init('https://vision.googleapis.com/v1/images:annotate?key=' . rawurlencode($apiKey));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Google Vision 요청 실패: ' . $curlErr);
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        throw new RuntimeException('Google Vision 응답을 해석하지 못했습니다.');
    }

    if ($httpCode >= 400) {
        $message = $json['error']['message'] ?? ('HTTP ' . $httpCode);
        throw new RuntimeException('Google Vision 오류: ' . $message);
    }

    $res = $json['responses'][0] ?? [];
    $text = (string)($res['fullTextAnnotation']['text'] ?? '');
    if ($text === '') {
        $text = (string)($res['textAnnotations'][0]['description'] ?? '');
    }

    return [
        'text' => trim($text),
        'raw' => $json,
    ];
}

function mock_ocr(string $imagePath): array
{
    return [
        'text' => "홍길동 팀장\n영업팀\n아주메디컬 주식회사\nMobile 010-1234-5678\nTel 031-555-1200\nhello@example.com\nwww.example.com\n경기도 수원시 영통구 ...",
        'raw' => ['mock' => true, 'image' => basename($imagePath)],
    ];
}

function parse_business_card_text(string $text, array $raw = []): array
{
    $text = trim($text);
    $lines = preg_split('/\R/u', $text) ?: [];
    $lines = array_values(array_filter(array_map(static function ($v) {
        $v = preg_replace('/\s+/u', ' ', trim((string)$v));
        $v = str_replace(['|', '｜', '•', '·'], ' ', $v);
        return trim($v);
    }, $lines)));

    $joined = implode("\n", $lines);

    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $joined, $emailMatches);
    $emails = array_values(array_unique(array_map('trim', $emailMatches[0] ?? [])));

    preg_match_all('/((https?:\/\/)?(www\.)?[\p{L}0-9\-]+(?:\.[\p{L}0-9\-]+)+(?:\/[^\s]*)?)/u', $joined, $urlMatches);
    $urls = [];
    foreach (($urlMatches[1] ?? []) as $u) {
        $u = trim((string)$u);
        if ($u === '' || str_contains($u, '@')) continue;
        if (!preg_match('~^https?://~iu', $u)) {
            $u = 'https://' . $u;
        }
        $urls[] = $u;
    }
    $urls = array_values(array_unique($urls));

    preg_match_all('/(\+?\d[\d\s().\-]{7,}\d)/u', $joined, $phoneMatches);
    $phones = [];
    foreach (($phoneMatches[1] ?? []) as $p) {
        $digits = preg_replace('/\D+/', '', (string)$p);
        if (strlen($digits) >= 8) {
            $phones[] = normalize_phone((string)$p);
        }
    }
    $phones = array_values(array_unique($phones));

    $mobile = '';
    $phone = '';
    foreach ($phones as $p) {
        $digits = preg_replace('/\D+/', '', $p);
        if ($mobile === '' && preg_match('/^(010|011|016|017|018|019)/', $digits)) {
            $mobile = $p;
            continue;
        }
        if ($phone === '') {
            $phone = $p;
        }
    }
    if ($mobile === '' && isset($phones[0])) $mobile = $phones[0];
    if ($phone === '' && isset($phones[1])) $phone = $phones[1];

    $name = '';
    $company = '';
    $jobTitle = '';
    $department = '';
    $address = '';

    foreach ($lines as $line) {
        if ($company === '' && is_company_line($line)) {
            $company = $line;
        }

        if ($department === '') {
            $tmpDept = extract_department_from_line($line);
            if ($tmpDept !== '') $department = $tmpDept;
        }

        if ($address === '' && preg_match('/(서울|경기|인천|부산|대구|광주|대전|울산|세종|제주|강원|충북|충남|전북|전남|경북|경남|로|길|동|구|읍|면|리|Address|주소)/iu', $line)) {
            $address = $line;
        }
    }

    // 1순위: 좌표/크기 기반 이름 후보
    $name = detect_name_from_layout($raw);

    // 2순위: 이름+직책 한 줄
    if ($name === '') {
        foreach ($lines as $line) {
            $split = split_name_and_title($line);
            if ($split['name'] !== '') {
                $name = $split['name'];
                if ($jobTitle === '' && $split['job_title'] !== '') {
                    $jobTitle = $split['job_title'];
                }
                break;
            }
        }
    }

    // 3순위: 이름 단독 줄
    if ($name === '') {
        foreach (array_slice($lines, 0, 6) as $line) {
            if (is_name_only_line($line)) {
                $name = $line;
                break;
            }
        }
    }

    // 직책은 이름이 잡힌 뒤에만 보수적으로 채택
    if ($jobTitle === '' && $name !== '') {
        foreach (array_slice($lines, 0, 8) as $line) {
            $tmpTitle = extract_job_title_from_line($line);

            // 이름 후보 줄이면 split 우선
            $split = split_name_and_title($line);
            if ($split['name'] === $name && $split['job_title'] !== '') {
                $jobTitle = $split['job_title'];
                break;
            }

            // 이름 없는 단독 직책 줄은 다음 우선순위
            if ($tmpTitle !== '' && trim($line) === $tmpTitle) {
                $jobTitle = $tmpTitle;
                break;
            }
        }
    }

    $memoParts = [];
    foreach ($lines as $line) {
        if (in_array($line, [$company, $address, $name], true)) continue;
        if ($department !== '' && $line === $department) continue;
        if ($jobTitle !== '' && $line === $jobTitle) continue;
        if (in_array($line, $emails, true)) continue;
        if (in_array($line, $phones, true)) continue;

        $normalizedLineUrl = 'https://' . ltrim((string)preg_replace('~^https?://~iu', '', $line), '/');
        if (in_array($normalizedLineUrl, $urls, true)) continue;

        $clean = trim($line);
        if ($clean !== '') $memoParts[] = $clean;
    }

    return [
        'name' => $name,
        'company' => $company,
        'job_title' => $jobTitle,
        'department' => $department,
        'mobile' => $mobile,
        'phone' => $phone,
        'email' => $emails[0] ?? '',
        'website' => $urls[0] ?? '',
        'address' => $address,
        'memo' => implode("\n", array_slice(array_values(array_unique($memoParts)), 0, 8)),
    ];
}

function detect_name_from_layout(array $raw): string
{
    $blocks = $raw['responses'][0]['fullTextAnnotation']['pages'][0]['blocks'] ?? [];
    if (!$blocks || !is_array($blocks)) {
        return '';
    }

    $candidates = [];

    foreach ($blocks as $block) {
        $text = block_to_text($block);
        $text = preg_replace('/\s+/u', ' ', trim($text));

        if ($text === '') continue;
        if (!preg_match('/^[가-힣]{2,4}(?:\s*[가-힣]{2,4})?$/u', $text)) continue;
        if (extract_job_title_from_line($text) !== '') continue;
        if (extract_department_from_line($text) !== '') continue;
        if (is_company_line($text)) continue;

        $box = $block['boundingBox']['vertices'] ?? [];
        $xs = array_column($box, 'x');
        $ys = array_column($box, 'y');
        $minX = min(array_map('intval', $xs ?: [0]));
        $maxX = max(array_map('intval', $xs ?: [0]));
        $minY = min(array_map('intval', $ys ?: [0]));
        $maxY = max(array_map('intval', $ys ?: [0]));
        $height = max(1, $maxY - $minY);
        $width = max(1, $maxX - $minX);

        $score = 0;

        // 위쪽일수록 가산점
        if ($minY < 250) $score += 20;
        elseif ($minY < 420) $score += 10;

        // 글자 크기가 클수록 가산점
        if ($height >= 40) $score += 25;
        elseif ($height >= 28) $score += 15;

        // 너무 긴 문장은 제외
        if (mb_strlen($text) >= 2 && mb_strlen($text) <= 4) $score += 20;

        // 좌측/중앙 근처 가산점
        if ($minX < 350) $score += 8;

        $candidates[] = [
            'text' => $text,
            'score' => $score,
            'y' => $minY,
            'h' => $height,
            'w' => $width,
        ];
    }

    if (!$candidates) {
        return '';
    }

    usort($candidates, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            return $a['y'] <=> $b['y'];
        }
        return $b['score'] <=> $a['score'];
    });

    return trim((string)$candidates[0]['text']);
}

function block_to_text(array $block): string
{
    $out = [];
    $paragraphs = $block['paragraphs'] ?? [];
    foreach ($paragraphs as $p) {
        $words = $p['words'] ?? [];
        $line = '';
        foreach ($words as $word) {
            $symbols = $word['symbols'] ?? [];
            foreach ($symbols as $sym) {
                $line .= (string)($sym['text'] ?? '');
            }
            $line .= ' ';
        }
        $out[] = trim($line);
    }
    return trim(implode(' ', $out));
}

function split_name_and_title(string $line): array
{
    $line = trim($line);
    if ($line === '') {
        return ['name' => '', 'job_title' => ''];
    }

    $titles = '(대표이사|대표원장|부사장|상무이사|전무이사|본부장|센터장|실장|팀장|부장|차장|과장|대리|주임|사원|이사|대표|원장|교수|소장|Manager|Director|CEO|CTO|CFO|Head|Lead)';
    $namePart = '([가-힣]{2,4}|[A-Za-z]{2,}(?:\s+[A-Za-z]{2,}){0,2})';

    if (preg_match('/^' . $namePart . '\s+' . $titles . '$/u', $line, $m)) {
        return [
            'name' => trim((string)$m[1]),
            'job_title' => trim((string)$m[2]),
        ];
    }

    if (preg_match('/^' . $titles . '\s+' . $namePart . '$/u', $line, $m)) {
        return [
            'name' => trim((string)$m[2]),
            'job_title' => trim((string)$m[1]),
        ];
    }

    if (preg_match('/^([가-힣]{2,4})(대표이사|대표원장|부사장|상무이사|전무이사|본부장|센터장|실장|팀장|부장|차장|과장|대리|주임|사원|이사|대표|원장|교수|소장)$/u', $line, $m)) {
        return [
            'name' => trim((string)$m[1]),
            'job_title' => trim((string)$m[2]),
        ];
    }

    return ['name' => '', 'job_title' => ''];
}

function is_name_only_line(string $line): bool
{
    $line = trim($line);
    if ($line === '') {
        return false;
    }
    if (mb_strlen($line) < 2 || mb_strlen($line) > 5) {
        return false;
    }
    if (!preg_match('/^[가-힣]{2,4}$/u', $line)) {
        return false;
    }
    if (extract_job_title_from_line($line) !== '') {
        return false;
    }
    if (extract_department_from_line($line) !== '') {
        return false;
    }
    if (is_company_line($line)) {
        return false;
    }
    if (preg_match('/\d|@|www|https?:\/\//iu', $line)) {
        return false;
    }

    return true;
}

function is_company_line(string $line): bool
{
    return preg_match('/(주식회사|㈜|\(주\)|Inc\.?|Corp\.?|Co\.?|Ltd\.?|신협|병원|의원|센터|학교|대학교|Medical|메디컬|테크|Tech)/iu', $line) === 1;
}

function extract_job_title_from_line(string $line): string
{
    $line = trim($line);

    if (preg_match('/(대표이사|대표원장|부회장|사장|부사장|전무|상무|상무이사|전무이사|본부장|지점장|센터장|실장|팀장|부장|차장|과장|대리|주임|사원|이사|대표|원장|교수|소장|Manager|Director|CEO|CTO|CFO|Head|Lead)/iu', $line, $m)) {
        return trim((string)$m[1]);
    }

    return '';
}

function extract_department_from_line(string $line): string
{
    if (preg_match('/([가-힣A-Za-z0-9]+(?:기획|영업|마케팅|총무|관리|행정|개발|전산|지원|운영|재무|인사)?(?:팀|부|실|센터|사업부|본부|파트|연구소))/u', $line, $m)) {
        $dept = trim((string)$m[1]);

        if (preg_match('/(팀장|부장|차장|과장|대리|주임|사원|실장)$/u', $dept)) {
            return '';
        }

        return $dept;
    }

    return '';
}

function normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if (str_starts_with($digits, '82')) {
        $digits = '0' . substr($digits, 2);
    }

    if (preg_match('/^02\d{8}$/', $digits)) {
        return preg_replace('/^(02)(\d{4})(\d{4})$/', '$1-$2-$3', $digits) ?: $digits;
    }

    if (preg_match('/^01\d{9}$/', $digits)) {
        return preg_replace('/^(01\d)(\d{4})(\d{4})$/', '$1-$2-$3', $digits) ?: $digits;
    }

    if (preg_match('/^0\d{9,10}$/', $digits)) {
        return preg_replace('/^(0\d{2})(\d{3,4})(\d{4})$/', '$1-$2-$3', $digits) ?: $digits;
    }

    return trim($raw);
}

function build_vcf(array $contact): string
{
    $e = function ($v) {
        $v = (string)$v;
        $v = str_replace("\\", "\\\\", $v);
        $v = str_replace(";", "\\;", $v);
        $v = str_replace(",", "\\,", $v);
        $v = str_replace("\n", "\\n", $v);
        return $v;
    };

    $name = trim($contact['name'] ?? '');
    $company = trim($contact['company'] ?? '');
    $job = trim($contact['job_title'] ?? '');
    $dept = trim($contact['department'] ?? '');
    $mobile = trim($contact['mobile'] ?? '');
    $phone = trim($contact['phone'] ?? '');
    $email = trim($contact['email'] ?? '');
    $address = trim($contact['address'] ?? '');

    if ($name === '') {
        $name = 'Unknown';
    }

    $lines = [];

    $lines[] = 'BEGIN:VCARD';
    $lines[] = 'VERSION:3.0';

    // ⭐ 핵심 (한글 깨짐 방지)
    $lines[] = 'N;CHARSET=UTF-8:' . $e($name) . ';;;;';
    $lines[] = 'FN;CHARSET=UTF-8:' . $e($name);

    if ($company !== '' || $dept !== '') {
        $lines[] = 'ORG;CHARSET=UTF-8:' . $e($company) . ';' . $e($dept);
    }

    if ($job !== '') {
        $lines[] = 'TITLE;CHARSET=UTF-8:' . $e($job);
    }

    if ($mobile !== '') {
        $lines[] = 'TEL;TYPE=CELL:' . $mobile;
    }

    if ($phone !== '') {
        $lines[] = 'TEL;TYPE=WORK:' . $phone;
    }

    if ($email !== '') {
        $lines[] = 'EMAIL:' . $email;
    }

    if ($address !== '') {
        $lines[] = 'ADR;CHARSET=UTF-8:;;' . $e($address) . ';;;;';
    }

    $lines[] = 'END:VCARD';

    // ⭐ CRLF 강제
    $vcf = implode("\r\n", $lines) . "\r\n";

    // ⭐ BOM 추가 (안드로이드 호환성 ↑)
    return "\xEF\xBB\xBF" . $vcf;
}