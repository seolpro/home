# Tesla Port Monitor v1.9.2

운영 추적 완성 단계 패치.

- Shanghai 실제 출항 + API024 검증 + 11자리 MRN을 지속 추적대상으로 유지
- `admin/tracking.php` 추가: 추적 대기 / 화물 대기 / Tesla 아님 / TESLA 확인 상태 확인
- 기존 6시간 주기 API001 재검사를 유지하여 적하목록이 늦게 등록되는 경우도 후속 크론에서 포착
- 알림톡 OFF(`PPURIO_ENABLED=false`)일 때 `notified_at`을 절대 기록하지 않도록 수정
- 알림톡 var5는 MOF 참고 출발항보다 API024 실제 전출항지를 우선 사용
- cron JSON에 `tracking_total`, `tracking_waiting` 추가
- 추가 SQL 없음(v1.9 SQL 실행 상태 기준)
