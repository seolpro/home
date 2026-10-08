# Tesla Port Monitor v1.8

## 목적
v1.7-final에서 성공한 해수부 → 관세청 API024 자동 교차검증을 유지하면서, MRN 11자리에서 API001 화물정보로 넘어가는 연결 지점을 진단합니다.

## 추가
- `admin/cargo_link.php` 화물연결 진단 화면
- MRN 11자리 구조 표시
- API024 검증 / cargMtNo 확보 / API001 연결 상태를 선박별로 표시
- 중국발 우선 후보를 상단에 표시

## 중요한 원칙
- MRN 11자리를 API001 cargMtNo로 임의 변환하지 않습니다.
- 화물관리번호는 MRN 외에 MSN/HSN 등 추가 식별요소가 필요한 별도 번호입니다.
- 현재 승인된 API024는 MRN까지, API001은 완성된 cargMtNo 또는 MBL/HBL부터 조회할 수 있습니다.
- 따라서 v1.8은 연결이 끊기는 정확한 지점을 관리화면에 노출하는 진단 버전입니다.

## 설치
- 기존 `config.php` 유지
- v1.7-final 위에 덮어쓰기
- DB 변경 없음
- `/tesla_port/admin/cargo_link.php` 확인
