# Tesla Port Monitor v1.6

## 핵심
- 승인된 관세청 API024 `입출항보고내역조회`를 호출부호(`shipCallImoNo`)로 자동 조회합니다.
- 해수부 Info5 자동차운반선의 `call_sign`과 관세청 선박명/입항시각을 비교하여 교차검증합니다.
- API024는 화물관리번호/품명을 반환하지 않으므로, MRN→cargMtNo를 임의 변환하지 않습니다.
- 기존 API001 상세조회는 `customs_cargo_no`가 실제 확보된 경우에만 유지합니다.

## 설치
1. 기존 `config.php` 보존
2. v1.6 파일 덮어쓰기
3. phpMyAdmin에서 `sql/upgrade_v1_6.sql` 1회 실행
4. 기존 config.php에 아래 한 줄만 추가(선택: 기본값이 같아서 생략 가능)
   `define('CUSTOMS_IOPR_API_URL','https://unipass.customs.go.kr:38010/ext/rest/ioprRprtQry/retrieveIoprRprtBrkd');`
5. `/tesla_port/admin/iopr_test.php`에서 실제 호출부호(예: 해수부 후보의 call sign) 테스트
6. 성공 후 cron/check.php 실행 결과의 `api024_checked`, `api024_verified` 확인

## 판정
- 관세청 선박명 == 해수부 선박명
- 입항시각 차이 72시간 이내
두 조건을 만족하면 `customs_iopr_verified=1`.
