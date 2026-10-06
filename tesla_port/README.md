# Tesla Port Monitor v1.3

## 중요: 기존 config.php 보존
이 ZIP에는 `config.php`를 넣지 않았습니다. 서버에 이미 입력된 인증키/CRON KEY/뿌리오 설정을 보호하기 위한 것입니다. 기존 `config.php`를 그대로 사용하세요.

v1.3은 기존 `PORT_AUTHORITY_CODE`도 인식하며, `LOOKBACK_DAYS`, `LOOKAHEAD_DAYS`가 없으면 각각 3일/14일을 기본값으로 사용합니다.

## v1.3 변경사항
- 해수부 Info5 전체 페이지 자동 순회(페이지당 최대 50건)
- 전체 선박을 DB에 저장하면서 `자동차운반선` 자동 분류
- 전출항지 국가항구코드 저장
- 중국발 자동차운반선 우선후보 자동 표시
- `admin/api_test.php`도 첫 50건이 아닌 전체 페이지를 합산
- `admin/carriers.php` 자동차운반선 후보 전용 화면 추가
- cron JSON에 전체 조회건수/자동차운반선/중국발 후보 건수 표시
- 기존 config 상수와 호환성 강화

## 업그레이드 순서 (v1.2 -> v1.3)
1. 현재 서버 폴더와 DB 백업
2. ZIP의 파일을 덮어쓰기 (`config.php`는 ZIP에 없으므로 기존 값 유지)
3. phpMyAdmin에서 `sql/upgrade_v1_3.sql`을 딱 1회 실행
4. `/tesla_port/admin/api_test.php?run=1` 확인
5. `/tesla_port/cron/check.php?key=기존_CRON_KEY` 1회 수동 실행
6. `/tesla_port/admin/carriers.php`에서 후보 확인

## 현재 자동판정 범위
v1.3은 선박종류가 `자동차운반선`인 항목을 자동 후보로 분류합니다. 중국발 표시는 전출항지 국가항구코드가 `CN`으로 시작하거나 알려진 중국 항만명이 확인되는 경우의 우선 필터입니다.

아직 `TESLA` 화물 자체를 자동 확정하지는 않습니다. 다음 단계에서 `mrNum`(적하목록관리번호)을 관세청 화물통관정보와 연결해 Tesla 화물을 판별합니다.
