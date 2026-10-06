# Tesla Port Monitor v1.8.2

- Tesla 추적 후보 판정의 권위값을 API024 실제 전출항지로 통일
- Shanghai / 上海 / 상하이만 후보로 인정
- MOF 수집 단계에서 기존 상하이 플래그를 덮어쓰던 오류 수정
- 화물연결 진단 화면에 API024 즉시 재검증 버튼 추가
- 화면에 API024 실제 전출항지와 MOF 참고 출발항을 분리 표시
- DB 스키마 변경 없음
- API001 연결은 실제 cargMtNo 또는 MBL/HBL 확보 전까지 추정하지 않음
