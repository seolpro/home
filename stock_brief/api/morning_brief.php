<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib.php';

$key = (string)($_GET['key'] ?? '');

if ($key === '' || !hash_equals((string)cfg('security.gas_key'), $key)) {
    json_out(['ok'=>false,'message'=>'인증키 오류'],403);
}

$mode = (string)($_GET['mode'] ?? 'send');

try {
    $brief = build_morning_brief();

    if ($mode === 'preview') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "등록 종목: ".$brief['stock_count']."개\n";
        echo "예상 발송 문자: ".count($brief['parts'])."건\n\n";
        foreach ($brief['parts'] as $i=>$part) {
            echo "===== ".($i+1)." / ".count($brief['parts'])." =====\n";
            echo $part."\n\n";
        }
        exit;
    }

    $phone = preg_replace('/\D/','',(string)cfg('brief.recipient_phone'));
    $name = trim((string)cfg('brief.recipient_name')) ?: '관리자';

    if (!preg_match('/^01\d{8,9}$/',$phone)) {
        throw new RuntimeException('config.php의 brief.recipient_phone을 설정하세요.');
    }

    // 오늘 이미 전체 브리핑이 성공 발송되었는지 확인
    $q = db()->prepare(
        "SELECT result_json FROM stock_sms_logs
         WHERE recipient=?
           AND created_at>=CURDATE()
           AND message LIKE '[주식 포트폴리오 아침 브리핑%'
         ORDER BY id DESC"
    );
    $q->execute([$phone]);

    foreach ($q->fetchAll() as $row) {
        $r = json_decode((string)($row['result_json']??''),true);
        if (is_array($r) && !empty($r['ok']) && !empty($r['brief_complete'])) {
            json_out([
                'ok'=>true,
                'duplicate'=>true,
                'message'=>'오늘 이미 성공 발송했습니다.'
            ]);
        }
    }

    $results = [];
    $allOk = true;
    $total = count($brief['parts']);

    foreach ($brief['parts'] as $i=>$part) {
        $result = send_sms($phone,$part,$name);
        $ok = (bool)($result['ok']??false);
        if (!$ok) $allOk = false;

        $logResult = $result;
        $logResult['part'] = $i+1;
        $logResult['parts_total'] = $total;
        $logResult['brief_complete'] = ($ok && $i === $total-1 && $allOk);

        log_sms($phone,$part,$logResult);
        $results[] = $logResult;

        if (!$ok) break;

        // 연속 API 요청 간 짧은 간격
        if ($i < $total-1) usleep(250000);
    }

    json_out([
        'ok'=>$allOk,
        'duplicate'=>false,
        'stock_count'=>$brief['stock_count'],
        'message_parts'=>$total,
        'results'=>$results,
        'preview'=>$brief['message']
    ],$allOk?200:500);

} catch (Throwable $e) {
    json_out(['ok'=>false,'message'=>$e->getMessage()],500);
}
