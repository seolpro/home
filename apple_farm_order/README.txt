사과농장 AS 접수 패치 v1.7

1. 기존 파일과 DB 백업.
2. phpMyAdmin에서 upgrade_as_v1_7.sql 1회 실행.
3. as.php -> /www/apple_farm_order/as.php
   admin/as.php -> /www/apple_farm_order/admin/as.php
4. 뿌리오 배송 알림톡 템플릿의 버튼명 "AS신청"으로 설정.
   모바일/PC 버튼 URL(고정): https://seolhopro.mycafe24.com/apple_farm_order/as.php
5. 관리자: https://seolhopro.mycafe24.com/apple_farm_order/admin/as.php
   기존 관리자 로그인 세션을 사용합니다.
6. 주문관리 화면에 메뉴를 추가하고 싶다면 admin/index.php 상단 actions 안에
   <a class="btn secondary" href="as.php">AS 접수관리</a>
   를 넣으세요. 기존 주문관리 파일은 이 패치에서 덮어쓰지 않습니다.

중요: AS신청 버튼은 고정 URL이므로 수령자는 주문번호와 수령자 전화번호를 입력하여 주문을 확인합니다.
주문번호를 모르는 고객을 위한 별도 본인확인/조회 기능은 포함되지 않았습니다.
AS 접수 알림톡 및 사진 첨부는 포함되지 않았습니다.
관리자 페이지는 기존 세션 인증을 사용합니다. 공개 AS 페이지에는 10초 세션 간격 제한이 있지만
실운영에서는 IP별 속도제한, CAPTCHA/WAF 및 개인정보 보존기간 정책을 추가로 적용하는 것이 좋습니다.
