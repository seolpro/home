<?php
declare(strict_types=1);

function cfg(?string $key = null) {
    static $c;
    if (!$c) {
        $f = __DIR__ . '/config.php';
        if (!is_file($f)) die('config.php가 없습니다.');
        $c = require $f;
        date_default_timezone_set($c['timezone'] ?? 'Asia/Seoul');
    }
    if ($key === null) return $c;
    $v = $c;
    foreach (explode('.', $key) as $p) $v = $v[$p] ?? null;
    return $v;
}

function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $d = cfg('db');
    $pdo = new PDO(
        "mysql:host={$d['host']};port=".($d['port']??3306).";dbname={$d['name']};charset=".($d['charset']??'utf8mb4'),
        $d['user'],
        $d['pass'],
        [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false
        ]
    );
    return $pdo;
}

function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function json_out(array $a, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_required(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['admin'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function verify_csrf(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        throw new RuntimeException('요청 확인값이 올바르지 않습니다.');
    }
}

function http_get(string $url, int $timeout = 15): string {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL 확장이 필요합니다.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_CONNECTTIMEOUT=>8,
        CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_USERAGENT=>'Mozilla/5.0 StockBrief/1.3',
        CURLOPT_HTTPHEADER=>['Accept: application/json,text/plain,*/*'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300) {
        throw new RuntimeException("외부 데이터 조회 실패 (HTTP {$code}) {$err}");
    }
    return (string)$body;
}

function provider_symbol(array $stock): string {
    $code = trim((string)$stock['symbol']);
    $market = (string)$stock['market'];
    if ($market === 'KR_KOSPI') return $code . '.KS';
    if ($market === 'KR_KOSDAQ') return $code . '.KQ';
    return strtoupper($code);
}

function fetch_quote(array $stock): array {
    $symbol = provider_symbol($stock);
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/'
         . rawurlencode($symbol)
         . '?range=10d&interval=1d&includePrePost=false&events=div%2Csplits';

    $j = json_decode(http_get($url), true);
    $r = $j['chart']['result'][0] ?? null;
    if (!$r) throw new RuntimeException($stock['name'].' 시세 응답 해석 실패');

    $timestamps = $r['timestamp'] ?? [];
    $closes = $r['indicators']['quote'][0]['close'] ?? [];
    $rows = [];

    foreach ($timestamps as $i => $ts) {
        $close = $closes[$i] ?? null;
        if ($close === null) continue;
        $rows[] = [
            'date'=>date('Y-m-d', (int)$ts),
            'close'=>(float)$close,
        ];
    }

    if (count($rows) < 2) {
        throw new RuntimeException($stock['name'].' 최근 종가 부족');
    }

    $latest = $rows[count($rows)-1];
    $prev = $rows[count($rows)-2];
    $change = $latest['close'] - $prev['close'];
    $changePct = $prev['close'] != 0 ? ($change/$prev['close'])*100 : 0;

    return [
        'symbol'=>$symbol,
        'date'=>$latest['date'],
        'close'=>$latest['close'],
        'previous_close'=>$prev['close'],
        'change'=>$change,
        'change_pct'=>$changePct,
        'currency'=>($stock['market']==='US')?'USD':'KRW',
    ];
}

function fetch_news(string $query, int $limit = 1): array {
    $query = trim($query);
    if ($query === '') return [];
    $url = 'https://news.google.com/rss/search?q='.rawurlencode($query).'&hl=ko&gl=KR&ceid=KR:ko';

    try {
        $xmlText = http_get($url);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlText);
        if (!$xml || empty($xml->channel->item)) return [];

        $out = [];
        foreach ($xml->channel->item as $item) {
            $title = trim((string)$item->title);
            if ($title === '') continue;
            $out[] = $title;
            if (count($out) >= $limit) break;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * 뿌리오 LMS 호환성을 높이기 위한 문자 정리.
 * 이모지/장식 특수문자를 일반문자로 치환하고
 * EUC-KR로 표현할 수 없는 문자는 제거합니다.
 */
function sms_safe_text(string $text): string {
    $map = [
        '📈'=>'',
        '📉'=>'',
        '📊'=>'',
        '📱'=>'',
        '•'=>'-',
        '●'=>'-',
        '▪'=>'-',
        '▲'=>'+',
        '▼'=>'-',
        '↑'=>'+',
        '↓'=>'-',
        '→'=>'->',
        '“'=>'"',
        '”'=>'"',
        '‘'=>"'",
        '’'=>"'",
        '…'=>'...',
        '–'=>'-',
        '—'=>'-',
        '·'=>'/',
    ];
    $text = strtr($text, $map);

    // 제어문자 제거(개행/탭은 유지)
    $text = preg_replace('/[^\P{C}\n\r\t]+/u', '', $text) ?? $text;

    // EUC-KR 왕복 변환으로 표현 불가능 문자 제거/대체
    $encoded = @iconv('UTF-8', 'EUC-KR//IGNORE', $text);
    if ($encoded !== false) {
        $decoded = @iconv('EUC-KR', 'UTF-8//IGNORE', $encoded);
        if ($decoded !== false) $text = $decoded;
    }

    // 불필요한 공백/개행 정리
    $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    return trim($text);
}

/**
 * LMS 한 건의 바이트를 보수적으로 제한하여 자동 분할.
 * 종목 수 제한 없이 여러 건으로 나누어 발송할 수 있습니다.
 */
function split_lms_messages(string $message, int $maxBytes = 1700): array {
    $message = sms_safe_text($message);
    $lines = preg_split('/\R/u', $message) ?: [$message];

    $parts = [];
    $current = '';

    foreach ($lines as $line) {
        $candidate = $current === '' ? $line : $current . "\n" . $line;
        $bytes = strlen(mb_convert_encoding($candidate, 'EUC-KR', 'UTF-8'));

        if ($bytes <= $maxBytes) {
            $current = $candidate;
            continue;
        }

        if ($current !== '') {
            $parts[] = trim($current);
            $current = '';
        }

        // 한 줄 자체가 너무 길면 문자 단위로 분할
        $buf = '';
        $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $test = $buf . $ch;
            $b = strlen(mb_convert_encoding($test, 'EUC-KR', 'UTF-8'));
            if ($b > $maxBytes && $buf !== '') {
                $parts[] = trim($buf);
                $buf = $ch;
            } else {
                $buf = $test;
            }
        }
        $current = $buf;
    }

    if (trim($current) !== '') $parts[] = trim($current);

    $total = count($parts);
    if ($total > 1) {
        foreach ($parts as $i => &$part) {
            $part = '[주식 포트폴리오 아침 브리핑 '.($i+1).'/'.$total."]\n".$part;
        }
        unset($part);
    }

    return $parts ?: [''];
}

function ppurio_token(): array {
    $s = cfg('sms');
    if (empty($s['account']) || empty($s['auth_key'])) {
        return ['ok'=>false,'message'=>'뿌리오 계정 또는 인증키가 없습니다.'];
    }

    $ch = curl_init('https://message.ppurio.com/v1/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_HTTPHEADER=>[
            'Authorization: Basic '.base64_encode($s['account'].':'.$s['auth_key']),
            'Content-Type: application/json; charset=utf-8'
        ],
    ]);

    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $json = json_decode((string)$body, true);
    $token = is_array($json) ? ($json['token'] ?? null) : null;

    return [
        'ok'=>(bool)$token,
        'token'=>$token,
        'code'=>$code,
        'body'=>$body,
        'error'=>$err,
        'message'=>$token?'토큰 발급 성공':'뿌리오 토큰 발급 실패'
    ];
}

function sms_type(string $message): string {
    $safe = sms_safe_text($message);
    $encoded = mb_convert_encoding($safe, 'EUC-KR', 'UTF-8');
    return strlen($encoded) <= 90 ? 'SMS' : 'LMS';
}

function send_sms(string $phone, string $message, string $name='수신자'): array {
    $s = cfg('sms');
    if (empty($s['enabled'])) return ['ok'=>false,'message'=>'문자 설정이 비활성화되어 있습니다.'];

    $message = sms_safe_text($message);
    $to = preg_replace('/\D/', '', $phone);
    $from = preg_replace('/\D/', '', (string)($s['sender'] ?? ''));

    if (!preg_match('/^01\d{8,9}$/', $to)) return ['ok'=>false,'message'=>'수신번호 형식 오류'];
    if (!$from) return ['ok'=>false,'message'=>'발신번호가 설정되지 않았습니다.'];

    $tk = ppurio_token();
    if (empty($tk['ok'])) return $tk;

    $payload = [
        'account'=>$s['account'],
        'messageType'=>sms_type($message),
        'content'=>$message,
        'from'=>$from,
        'duplicateFlag'=>'Y',
        'targetCount'=>1,
        'refKey'=>'stock_'.date('YmdHis').'_'.bin2hex(random_bytes(4)),
        'targets'=>[[
            'to'=>$to,
            'name'=>$name,
            'changeWord'=>['var1'=>$name]
        ]]
    ];

    $ch = curl_init('https://message.ppurio.com/v1/message');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>30,
        CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER=>[
            'Authorization: Bearer '.$tk['token'],
            'Content-Type: application/json; charset=utf-8'
        ],
    ]);

    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $json = json_decode((string)$body, true);
    $apiCode = is_array($json) ? (string)($json['code'] ?? '') : '';
    $ok = $code>=200 && $code<300 && ($apiCode==='' || in_array($apiCode,['1000','200'],true));

    return [
        'ok'=>$ok,
        'http_code'=>$code,
        'api_code'=>$apiCode,
        'response'=>$json ?: $body,
        'error'=>$err,
        'message'=>$ok?'문자 발송 요청 성공':'문자 발송 실패'
    ];
}

function log_sms(string $phone, string $message, array $result): void {
    $q = db()->prepare(
        'INSERT INTO stock_sms_logs(recipient,message,result_json,created_at) VALUES(?,?,?,NOW())'
    );
    $q->execute([
        $phone,
        $message,
        json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    ]);
}

function build_morning_brief(): array {
    $stocks = db()->query(
        "SELECT * FROM portfolio WHERE is_active=1 ORDER BY sort_order ASC,id ASC"
    )->fetchAll();

    if (!$stocks) throw new RuntimeException('활성 포트폴리오 종목이 없습니다.');

    $lines = [];
    $lines[] = '[주식 포트폴리오 아침 브리핑]';
    $lines[] = date('Y.m.d');
    $lines[] = '';

    $details = [];
    $krValue = $usValue = $krCost = $usCost = 0.0;

    foreach ($stocks as $stock) {
        try {
            $q = fetch_quote($stock);
            $qty = (float)$stock['quantity'];
            $avg = (float)$stock['avg_price'];
            $value = $q['close'] * $qty;
            $cost = $avg * $qty;

            if ($q['currency']==='KRW') {
                $krValue += $value;
                $krCost += $cost;
                $priceText = number_format((int)round($q['close'])).'원';
            } else {
                $usValue += $value;
                $usCost += $cost;
                $priceText = '$'.number_format($q['close'],2);
            }

            $sign = $q['change_pct'] > 0 ? '+' : ($q['change_pct'] < 0 ? '-' : '');

            $lines[] = $stock['name'];
            $lines[] = $q['date'].' 종가 '.$priceText
                     .' / '.$sign.number_format(abs($q['change_pct']),2).'%';

            if (!empty(cfg('brief.include_news'))) {
                $news = fetch_news(
                    trim((string)($stock['news_keyword'] ?: $stock['name'])),
                    max(1,(int)(cfg('brief.max_news_per_stock') ?: 1))
                );
                foreach ($news as $headline) {
                    $lines[] = '- '.$headline;
                }
            }
            $lines[] = '';

            $details[] = ['stock'=>$stock,'quote'=>$q,'value'=>$value,'cost'=>$cost];
        } catch (Throwable $e) {
            $lines[] = $stock['name'].': 시세 조회 실패';
            $lines[] = '';
            $details[] = ['stock'=>$stock,'error'=>$e->getMessage()];
        }
    }

    $lines[] = '[보유 평가]';

    if ($krCost > 0 || $krValue > 0) {
        $pnl = $krValue-$krCost;
        $pct = $krCost!=0 ? ($pnl/$krCost)*100 : 0;
        $lines[] = '국내 '.number_format((int)round($krValue)).'원'
                 .' / 평가손익 '.($pnl>=0?'+':'').number_format((int)round($pnl)).'원'
                 .' ('.($pct>=0?'+':'').number_format($pct,2).'%)';
    }

    if ($usCost > 0 || $usValue > 0) {
        $pnl = $usValue-$usCost;
        $pct = $usCost!=0 ? ($pnl/$usCost)*100 : 0;
        $lines[] = '미국 $'.number_format($usValue,2)
                 .' / 평가손익 '.($pnl>=0?'+':'').'$'.number_format($pnl,2)
                 .' ('.($pct>=0?'+':'').number_format($pct,2).'%)';
    }

    $message = sms_safe_text(implode("\n", $lines));

    return [
        'message'=>$message,
        'parts'=>split_lms_messages($message),
        'details'=>$details,
        'stock_count'=>count($stocks)
    ];
}
