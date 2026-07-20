<?php
require_once __DIR__.'/../core/Notification.php';
function phone_clean($v){ return Notification::phone((string)$v); }
function send_alimtalk($event,$recipientType,$recipientId,$phone,array $vars): bool {
    $normalized=[];
    foreach($vars as $k=>$v){
        if(preg_match('/^var(\d+)$/',(string)$k,$m)) $normalized['var'.$m[1]]=$v;
        else $normalized[$k]=$v;
    }
    return Notification::dispatch((string)$event,(string)$recipientType,$recipientId!==null?(int)$recipientId:null,(string)$phone,$normalized);
}


/**
 * 최종 승인된 휴가를 알림톡 수신동의 직원에게 안내합니다.
 * 신청자는 기존 승인완료 알림을 받으므로 중복발송 방지를 위해 제외합니다.
 * 반환값: ['target'=>대상수, 'queued_or_sent'=>등록수]
 */
function send_final_approval_broadcast(array $request): array {
    if (setting('final_approval_broadcast_enabled', '0') !== '1') {
        return ['target'=>0, 'queued_or_sent'=>0];
    }

    $applicantId = (int)($request['employee_id'] ?? 0);
    $pdo = db();
    $sql = "SELECT id,name,position,phone
            FROM employees
            WHERE is_active=1
              AND alimtalk_opt_in=1
              AND phone IS NOT NULL
              AND TRIM(phone)<>''";
    $params = [];
    if ($applicantId > 0) {
        $sql .= " AND id<>?";
        $params[] = $applicantId;
    }
    $sql .= " ORDER BY sort_order ASC,id ASC";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $seen = [];
    $target = 0;
    $sent = 0;
    while ($employee = $st->fetch(PDO::FETCH_ASSOC)) {
        $phone = phone_clean((string)$employee['phone']);
        if ($phone === '' || isset($seen[$phone])) continue;
        $seen[$phone] = true;
        $target++;
        $ok = send_alimtalk('approved_all_staff', 'employee', (int)$employee['id'], $phone, [
            'var1'=>trim((string)($request['employee_name'] ?? '').' '.(string)($request['position'] ?? '')),
            'var2'=>(string)($request['leave_name'] ?? ''),
            'var3'=>(string)($request['start_date'] ?? ''),
            'var4'=>(string)($request['end_date'] ?? ''),
            'var5'=>fmt_days($request['requested_days'] ?? 0),
        ]);
        if ($ok) $sent++;
    }
    return ['target'=>$target, 'queued_or_sent'=>$sent];
}
