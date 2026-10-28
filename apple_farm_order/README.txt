사과농장 AS 관리자 알림톡 패치 v1.9
1. 기존 as.php, config.php 백업
2. upgrade_as_alimtalk_v1_9.sql 실행 (AS v1.7 테이블 설치 필요)
3. as.php, as_alimtalk.php를 /apple_farm_order/에 업로드
4. v1.5의 order_alimtalk.php가 같은 폴더에 있어야 함
5. config_add_example.txt의 as_alimtalk 항목을 기존 config.php 배열에 추가
6. 뿌리오 기본형 텍스트 템플릿 승인 후 enabled=true
7. AS 접수 테스트, 관리자 수신 및 DB 로그 확인

템플릿 본문 (승인용):
[진이네농장 AS 접수 알림]

새로운 AS 신청이 접수되었습니다.
수령자: [*1*]
상품: [*2*]
AS 유형: [*3*]
AS 접수번호: [*4*]

관리자 페이지에서 신청 내용을 확인해 주세요.

버튼명: AS 접수 확인
버튼 종류: 웹링크
모바일/PC 고정 URL: https://seolhopro.mycafe24.com/apple_farm_order/admin/as.php

변수 매핑: var1=수령자, var2=상품명, var3=AS 유형, var4=AS 접수번호.
승인 템플릿의 변수 형식이 위와 일치하는지 확인.
접수 저장 성공 후 발송하며 API 실패 시에도 AS 접수 유지.
동일 AS ID에 대한 자동 중복 발송은 차단. 실패 건은 로그 확인 후 수동 대응.
