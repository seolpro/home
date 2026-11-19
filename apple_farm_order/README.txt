사과농장 주문관리 입금확인 패치 v2.4

1. DB와 admin/index.php, admin/update.php 백업
2. phpMyAdmin에서 upgrade_payment.sql 1회 실행 (이미 컬럼이 있다면 재실행하지 마세요)
3. admin/index.php, admin/update.php 업로드
4. 입금확인 체크 및 메모 저장, 미입금 필터, 배송처리와 알림톡 테스트

기존 주문은 payment_confirmed=0(미입금)으로 초기 표시됩니다. 실제 입금 여부는 관리자가 통장 내역과 대조하세요.
입금 확인일시는 최초 체크 시 저장되고, 체크 상태를 유지한 메모 수정 시 보존됩니다. 체크 해제 시 확인일시가 삭제됩니다.
기존 export.php는 변경하지 않았으므로 CSV 입금컬럼과 입금필터 적용은 별도 수정이 필요합니다.
인쇄용 목록에는 입금상태, 일시, 메모가 표시됩니다.
