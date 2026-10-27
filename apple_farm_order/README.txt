사과농장 주문접수 관리자 알림톡 패치 v1.5

1. 기존 index.php 백업
2. index.php, order_alimtalk.php를 /www/apple_farm_order/ 에 업로드
3. phpMyAdmin에서 install_alimtalk_log.sql 실행 (로그 테이블)
4. config_add_example.txt 내용을 기존 config.php 배열에 추가 (기존 설정 유지)
5. 뿌리오에서 템플릿 승인 후 enabled=true 변경
6. 테스트 주문으로 주문 저장과 관리자 수신 확인

[뿌리오 알림톡 템플릿 - 기본형 텍스트]
[사과농장 신규 주문 접수]
신규 주문이 접수되었습니다.
수령자: [*1*]
상품: [*2*]
수량: [*3*]개
주문번호: [*4*]
관리자 페이지에서 주문 및 배송정보를 확인해 주세요.

변수1=수령자 성명, 변수2=상품명, 변수3=수량, 변수4=주문번호
버튼: 주문목록 확인 / 고정URL https://seolhopro.mycafe24.com/apple_farm_order/admin/
(실제 운영 URL이 다르면 템플릿 승인 전 수정)
버튼 URL에 토큰 변수를 넣지 않습니다.

주의: 주문 DB 저장 성공 후 발송합니다. 발송 실패 시 주문은 유지됩니다.
발송 로그는 apple_order_alimtalk_logs 테이블에서 확인합니다.
실제 발송 성공 여부는 뿌리오 발송 결과와 수신 상태로 확인하세요.
