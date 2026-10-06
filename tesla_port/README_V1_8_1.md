# Tesla Port Monitor v1.8.1

## 변경점
- Tesla 추적 후보를 중국 전체 항만이 아니라 **API024 실제 전출항지가 Shanghai인 자동차운반선**으로 제한합니다.
- Shanghai / 上海 / 상하이 표기를 인식합니다.
- Tianjin, Ningbo, Vladivostok, Hakata 등은 후보에서 제외합니다.
- API024 재검증 시 기존 후보 플래그를 그대로 유지하지 않고 현재 전출항지 기준으로 0/1을 다시 기록합니다.
- 기존 DB 컬럼 `china_origin_flag`는 DB 변경 없이 호환성을 위해 그대로 사용하지만, v1.8.1부터 의미는 `상하이 출항 Tesla 추적 후보`입니다.
- MRN → cargMtNo/B/L은 근거 없는 변환을 하지 않으며 기존 진단 상태를 유지합니다.

## 설치
- v1.8 위에 덮어쓰기
- config.php 유지
- DB 변경/SQL 실행 없음
- 설치 직후 기존 API024 조회가 12시간 이내라면 cron이 재조회하지 않을 수 있습니다. 다음 정상 재조회 시 상하이 기준 플래그가 정리됩니다.
