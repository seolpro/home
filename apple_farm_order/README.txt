사과농장 추천인 입력란 패치 v1.2

1. 기존 파일을 백업합니다.
2. 카페24 phpMyAdmin에서 upgrade_v1_2.sql을 1회 실행합니다. (기존 install.sql 재실행 금지)
3. index.php -> /apple_farm_order/index.php
   admin/index.php -> /apple_farm_order/admin/index.php
   admin/export.php -> /apple_farm_order/admin/export.php
4. 추천인은 선택 입력이며, 관리자 검색·인쇄·CSV에 포함됩니다.
5. config.php 및 관리자 비밀번호 파일은 변경하지 않습니다.
주의: SQL 적용 전에 PHP를 업로드하면 주문 저장이 실패할 수 있습니다.
