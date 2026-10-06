# Tesla Port Monitor v1.7-final

실제 서버 테스트 결과를 반영한 v1.7 확정본입니다.

## 확정 사항
- 해수부 Info5 평택항 조회 정상
- 관세청 API024 호출부호 조회 정상 (`3FEN2` → `TRANS FUTURE 10`)
- 해수부 mrNum과 API024 입출항제출번호 동일값 확인
- API001 / API024 인증키 완전 분리
- `admin/iopr_test.php`의 중복 `h()` 선언 제거
- API024 출발항 기준 중국발 우선후보 판정
- MRN 11자리를 API001 cargMtNo로 임의 변환하지 않음

## 기존 v1.6에서 업그레이드
1. 기존 `config.php`는 덮어쓰지 않습니다.
2. 파일을 덮어씁니다.
3. v1.6 DB 업그레이드를 이미 실행했다면 추가 SQL은 없습니다.
4. 기존 `config.php`에 API001/API024 키가 각각 올바르게 분리되어 있는지 확인합니다.

```php
define('CUSTOMS_API_KEY', 'API001 화물통관진행정보조회 인증키');
define('CUSTOMS_IOPR_API_KEY', 'API024 입출항보고내역조회 인증키');
```

`CUSTOMS_IOPR_API_URL`은 생략해도 코드의 기본 URL을 사용합니다.

## 설치 후 확인
- `/admin/iopr_test.php?call_sign=3FEN2` : API024 테스트
- `/admin/customs_test.php` : API001 테스트
- `/admin/carriers.php` : 자동 교차검증 결과 확인
- 마지막으로 cron 수동 실행 후 `api024_checked`, `api024_verified` 확인

## 다음 개발 단계
API024의 11자리 입출항제출번호/MRN에서 API001의 cargMtNo 또는 MBL/HBL로 연결하는 공식 연결고리를 확인한 뒤 Tesla 품명/수량/중량 자동판정을 연결합니다.
