stock_brief v2 FULL (GAS 없음)
==============================

[구조]
Cafe24 서버 스케줄러(Cron) -> cron/morning_brief.php -> 시세/뉴스 수집 -> DB stock_briefs 저장 -> 뿌리오 알림톡 -> [브리핑 열기] -> brief.php

[설치]
1. 이 폴더를 기존 stock_brief와 다른 새 폴더(예: /stock_brief_v2/)에 업로드합니다.
2. config.php의 DB, 관리자 비밀번호 해시, 뿌리오 계정/인증키/발신번호/발신프로필/템플릿코드, 수신번호를 설정합니다.
3. 브라우저에서 upgrade_v2.php를 1회 실행한 뒤 삭제합니다. 최초 설치라면 install.php도 1회 실행합니다.
4. 관리자에서 포트폴리오 종목을 등록합니다.
5. brief.php가 외부에서 정상 열리는지 확인합니다.
6. 뿌리오 알림톡 템플릿 버튼을 '웹링크'로 등록하고 URL을 https://YOUR-DOMAIN/stock_brief_v2/brief.php 로 고정합니다.
7. config.php alimtalk.content는 승인받은 템플릿 본문과 정확히 동일하게 입력합니다.
8. api/morning_brief.php?mode=preview&key=... 로 브리핑 내용을 먼저 확인합니다.
9. api/morning_brief.php?mode=send&key=... 로 1회 시험 발송합니다.

[Cron - GAS 불필요]
Cafe24의 예약작업/크론에서 매일 오전 08:00에 PHP CLI 실행을 등록합니다.
예시 명령(실제 PHP 경로/계정 경로는 Cafe24 환경에 맞게 설정):
php /home/hosting_users/계정/www/stock_brief_v2/cron/morning_brief.php

호스팅 상품에서 PHP CLI 예약작업을 제공하지 않고 URL 호출 방식만 제공한다면 다음 보호 URL을 호출해도 됩니다.
https://YOUR-DOMAIN/stock_brief_v2/api/morning_brief.php?mode=send&key=CHANGE_TO_LONG_RANDOM_KEY

[중복 방지]
같은 날짜에 성공한 알림톡이 있으면 브리핑 데이터는 갱신하지만 알림톡은 다시 보내지 않습니다.
강제 재발송은 관리자 확인 후 api/morning_brief.php?mode=force&key=... 를 사용합니다.

[알림톡 승인 권장안]
본문:
📈 주식투자 아침 브리핑
오늘의 주식시장 브리핑이 업데이트되었습니다.
아래 버튼을 눌러 상세 내용을 확인해 주세요.

버튼명: 브리핑 열기
버튼 URL: https://YOUR-DOMAIN/stock_brief_v2/brief.php

※ 실제 승인된 템플릿 내용/버튼 설정과 config.php 값이 일치해야 합니다.
※ 템플릿 승인 자체는 카카오/뿌리오 심사 결과에 따릅니다.

[보안]
배포본 config.php에는 실제 비밀번호/인증키를 넣지 않았습니다.
기존 원본 ZIP에 포함되어 있던 인증정보는 보안을 위해 재사용하지 않았습니다. 필요하면 기존 비공개 설정 파일 방식으로 옮겨 사용하세요.
