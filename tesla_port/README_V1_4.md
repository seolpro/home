# Tesla Port Monitor v1.4

## 핵심
- v1.3 전체 페이지 자동조회/자동차운반선 분류 유지
- 관세청 UNI-PASS API001 `화물통관 진행정보` 연계 추가
- `admin/customs_test.php`: 관세청 API 단독 테스트
- `admin/carriers.php`: 자동차운반선별 관세청 화물관리번호(cargMtNo) 조회/저장
- 관세청 품명(`prnm`)에 `TESLA`가 있으면 `tesla_flag=1` 자동 반영
- 통관 진행상태(`prgsStts`), 총중량(`ttwg`), 포장개수(`pckGcnt`) 저장
- cron은 관세청 화물관리번호가 확보된 후보를 6시간 간격으로 재확인

## 중요: 해수부 mrNum과 관세청 cargMtNo
해수부 선박운항정보의 `mrNum`은 화면에서 11자리 값이 확인되었습니다.
반면 업로드된 관세청 API001 공식 가이드는 `cargMtNo`를 15~19자리로 명시합니다.
따라서 v1.4는 두 값을 임의로 동일시하거나 자릿수를 만들어 붙이지 않습니다.
관세청 `cargMtNo`가 확보된 건만 API001 상세조회합니다.

## 기존 v1.3 업데이트
1. 서버/DB 백업
2. v1.4 파일 덮어쓰기 (`config.php`는 ZIP에 포함하지 않음)
3. phpMyAdmin에서 `sql/upgrade_v1_4.sql` 1회 실행
4. 기존 `config.php`에 아래 두 줄 추가

```php
define('CUSTOMS_API_KEY', 'UNI-PASS에서 발급받은 실제 인증키');
define('CUSTOMS_API_URL', 'https://unipass.customs.go.kr:38010/ext/rest/cargCsclPrgsInfoQry/retrieveCargCsclPrgsInfo');
```

5. `/tesla_port/admin/customs_test.php`에서 15~19자리 관세청 화물관리번호로 테스트

## 보안
실제 인증키, CRON KEY, 뿌리오 키는 배포 ZIP에 포함하지 않습니다.
