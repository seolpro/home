# v1.9.3 알림톡 안전 테스트
- admin/alimtalk_test.php 추가
- 관리자 메뉴에 알림톡 테스트 버튼 추가
- 테스트 페이지에서만 PPURIO_ENABLED=false를 우회해 실제 1건 발송 가능
- 운영 cron의 PPURIO_ENABLED 동작은 기존 그대로 유지
- 테스트 발송은 tp_vessels.notified_at, 수신자 DB 등 운영 DB를 변경하지 않음
- 인증키/토큰/senderKey는 결과 화면에 출력하지 않음
- SQL 변경 없음
