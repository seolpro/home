# Tesla Port Monitor v1.7

## 핵심 변경
- API001과 API024 인증키 완전 분리
- `CUSTOMS_IOPR_API_KEY` 사용
- `admin/iopr_test.php`의 `h()` 중복 선언 제거
- 해수부 `mrNum`과 API024 `입출항제출번호`를 화면에서 비교 가능
- API024에서 확인된 출발항이 중국이면 `china_origin_flag=1`로 승격
- API024는 선박 교차검증용, API001은 cargMtNo/MBL/HBL 화물조회용으로 역할 분리

## config.php에 추가
기존 API001 키는 그대로 유지하고 API024 키를 별도로 추가합니다.

```php
define('CUSTOMS_IOPR_API_KEY', 'API024에서 발급받은 인증키');
```

URL은 코드에 기본값이 있으므로 `CUSTOMS_IOPR_API_URL`은 생략해도 됩니다.

## DB
v1.6의 `sql/upgrade_v1_6.sql`을 이미 실행했다면 DB 변경은 없습니다.
`sql/upgrade_v1_7.sql`은 확인용이며 스키마를 변경하지 않습니다.

## 테스트
1. `/tesla_port/admin/iopr_test.php?call_sign=3FEN2`
2. HTTP 200 / tCnt / 파싱 건수 확인
3. `/tesla_port/cron/check.php?key=...` 실행
4. `admin/carriers.php`에서 API024 교차검증 결과 확인

## 안전 원칙
- 11자리 `mrNum`/입출항제출번호를 API001 `cargMtNo`로 변환하지 않습니다.
- 실제 15~19자리 cargMtNo 또는 MBL/HBL이 확보된 경우에만 API001을 호출합니다.
