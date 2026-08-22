[오류 원인]

현재 stock_brief가 임대차 시스템과 같은 DB를 사용하고 있고,
그 DB에는 기존 임대차용 sms_logs 테이블이 이미 존재합니다.

임대차용 sms_logs는 contract_id가 NOT NULL인데,
주식 브리핑의 log_sms()는 contract_id 없이 INSERT 하므로
아래 오류가 발생합니다.

SQLSTATE[HY000]: General error: 1364
Field 'contract_id' doesn't have a default value


[수정 원칙]

기존 임대차 sms_logs 테이블은 건드리지 않습니다.
주식 브리핑 전용 stock_sms_logs 테이블을 새로 사용합니다.


1) upgrade_stock_sms_logs.php
stock_brief 루트에 올린 뒤 브라우저로 1회 실행하고 삭제합니다.


2) stock_brief/lib.php의 log_sms() 함수만 아래 코드로 교체하세요.

기존:

function log_sms(string $phone, string $message, array $result): void {
    $s = db()->prepare(
        'INSERT INTO sms_logs(recipient,message,result_json,created_at) VALUES(?,?,?,NOW())'
    );
    $s->execute([
        $phone,
        $message,
        json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
}

수정:

function log_sms(string $phone, string $message, array $result): void {
    $s = db()->prepare(
        'INSERT INTO stock_sms_logs(recipient,message,result_json,created_at) VALUES(?,?,?,NOW())'
    );
    $s->execute([
        $phone,
        $message,
        json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
}


3) stock_brief/api/morning_brief.php에서 중복발송 조회 테이블 변경

기존:
FROM sms_logs

수정:
FROM stock_sms_logs


4) stock_brief/admin/logs.php에서 조회 테이블 변경

기존:
SELECT * FROM sms_logs

수정:
SELECT * FROM stock_sms_logs


이렇게 하면 임대차 시스템 문자로그와 주식 브리핑 문자로그가 완전히 분리됩니다.
