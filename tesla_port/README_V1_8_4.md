# Tesla Port Monitor v1.8.4

## 목적
Shanghai 실제 출항 이력에서 확보한 API024 MRN을 화물 연결 표본으로 사용합니다.

## 추가
- `admin/mrn_bridge.php`: MRN 화물연결 실험실
- `admin/shanghai_history.php`: 각 Shanghai 실제 일치 건에 `이 MRN으로 화물연결 실험` 버튼
- API001은 공식 지원 입력만 사용
  - cargMtNo 15~19자리
  - MBL + 입항년도
  - HBL + 입항년도
- MRN 11자리를 cargMtNo로 임의 변환하지 않음

## 설치
v1.8.3 위에 덮어쓰기. config.php 유지. DB 변경 없음.
