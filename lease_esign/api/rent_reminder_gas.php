<?php
declare(strict_types=1);

/**
 * GAS 호출용 월차임 납부 전일 문자 발송 엔드포인트
 * 위치: lease_esign/api/rent_reminder_gas.php
 */
require_once dirname(__DIR__) . '/lib.php';

const GAS_TRIGGER_KEY = '767a780ea3766623db4a9f0366a1c280ffced49aa164a73393ddfd078b3e5505';

header('Content-Type: application/json; charset=utf-8');

$key = (string)($_GET['key'] ?? '');
if ($key === '' || !hash_equals(GAS_TRIGGER_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'인증키가 올바르지 않습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$today = new DateTimeImmutable('today', new DateTimeZone((string)(cfg('timezone') ?: 'Asia/Seoul')));
$tomorrow = $today->modify('+1 day');
$todayYmd = $today->format('Y-m-d');
$tomorrowYmd = $tomorrow->format('Y-m-d');
$tomorrowDay = (int)$tomorrow->format('j');
$lastDay = (int)$tomorrow->format('t');

function rr_digits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

function rr_already_sent(int $contractId, string $phone, string $todayYmd): bool {
    $st = db()->prepare(
        "SELECT result_json FROM sms_logs
         WHERE contract_id=?
           AND recipient=?
           AND message LIKE ?
           AND created_at >= ?
           AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY id DESC"
    );
    $st->execute([
        $contractId, $phone, '[임대차 월차임 납부 안내]%',
        $todayYmd.' 00:00:00', $todayYmd.' 00:00:00'
    ]);
    foreach ($st->fetchAll() as $row) {
        $r = json_decode((string)($row['result_json'] ?? ''), true);
        if (is_array($r) && !empty($r['ok'])) return true;
    }
    return false;
}

try {
    $contracts = db()->query("SELECT * FROM contracts ORDER BY id ASC")->fetchAll();

    $checked = $targets = $sent = $skipped = $failed = 0;
    $details = [];

    foreach ($contracts as $c) {
        $checked++;
        $id = (int)($c['id'] ?? 0);
        $name = trim((string)($c['lessee_name'] ?? '')) ?: '임차인';
        $phone = rr_digits((string)($c['lessee_phone'] ?? ''));
        $rent = (int)($c['monthly_rent'] ?? 0);
        $account = trim((string)($c['rent_account'] ?? ''));
        $startDate = substr(trim((string)($c['start_date'] ?? '')), 0, 10);
        $endDate = substr(trim((string)($c['end_date'] ?? '')), 0, 10);

        if ($id < 1 || $rent <= 0 || $account === '' || !preg_match('/^01\d{8,9}$/', $phone)) continue;
        if ($startDate !== '' && $tomorrowYmd < $startDate) continue;
        if ($endDate !== '' && $tomorrowYmd > $endDate) continue;

        if (array_key_exists('lessor_signed_at', $c) && empty($c['lessor_signed_at'])) continue;
        if (array_key_exists('lessee_signed_at', $c) && empty($c['lessee_signed_at'])) continue;

        $paymentDay = (int)($c['payment_day'] ?? 0);
        if ($paymentDay < 1 || $paymentDay > 31) {
            if ($startDate === '') continue;
            $paymentDay = (int)date('j', strtotime($startDate));
        }

        if ($tomorrowDay !== min($paymentDay, $lastDay)) continue;
        $targets++;

        if (rr_already_sent($id, $phone, $todayYmd)) {
            $skipped++;
            $details[] = ['id'=>$id,'name'=>$name,'status'=>'duplicate'];
            continue;
        }

        $message =
            "[임대차 월차임 납부 자동안내]\n\n"
            . $name . "님, 내일은 월차임 납부일입니다.\n\n"
            . "월차임 " . number_format($rent) . "원을 다음 계좌로 입금해 주세요.\n\n"
            . "[입금계좌]\n" . $account . "\n\n"
            . "본 SMS알림은 시스템에서 자동발송 되었습니다.";

        $result = send_sms($phone, $message, $name);
        sms_log($id, $phone, $message, $result);
        $ok = (bool)($result['ok'] ?? false);

        try {
            audit($id, 'system', 'rent_payment_reminder_sent', [
                'recipient'=>$phone,
                'payment_date'=>$tomorrowYmd,
                'monthly_rent'=>$rent,
                'sms_ok'=>$ok,
                'source'=>'gas',
                'result'=>$result
            ]);
        } catch (Throwable $ignore) {}

        if ($ok) $sent++; else $failed++;
        $details[] = ['id'=>$id,'name'=>$name,'status'=>$ok?'sent':'failed','message'=>$result['message'] ?? ''];
    }

    echo json_encode([
        'ok'=>$failed === 0,
        'today'=>$todayYmd,
        'payment_date'=>$tomorrowYmd,
        'checked'=>$checked,
        'targets'=>$targets,
        'sent'=>$sent,
        'duplicate_skipped'=>$skipped,
        'failed'=>$failed,
        'details'=>$details
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
