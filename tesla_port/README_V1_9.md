# Tesla Port Monitor v1.9

핵심: API024에서 교차검증된 Shanghai 출항 자동차운반선의 11자리 MRN을 대상으로 API001에 MRN+MSN 후보를 제한적으로 조회합니다.

## 설치
1. v1.8.6.2 위에 파일 덮어쓰기
2. `sql/upgrade_v1_9.sql` 1회 실행 (필수)
3. 기존 `config.php` 유지
4. 선택: config.php에 `CUSTOMS_MSN_SCAN_MAX=20`, `CUSTOMS_MSN_MISS_LIMIT=5` 설정. 없으면 기본값 사용.
5. 기존 cron URL을 브라우저에서 1회 실행하거나 GAS 정기 실행을 기다림.
6. 관리자 > 자동 화물탐색에서 결과 확인.

## 안전장치
- API024 교차검증 완료 + Shanghai + 11자리 MRN + 자동차운반선만 대상
- API001 실제 응답이 있는 화물만 저장
- 품명(prnm)에 TESLA가 있어야 Tesla 판정
- 기본 최대 20건, 연속 5건 미응답 시 중단
- 동일 MRN은 6시간 이내 반복 스캔하지 않음
- 알림톡 설정은 변경하지 않음. 현재 PPURIO_ENABLED=false이면 발송되지 않음.

## 성공 시 cron JSON 추가 항목
- `manifest_api001_calls`
- `manifest_valid_cargo`
- `manifest_tesla_items`
