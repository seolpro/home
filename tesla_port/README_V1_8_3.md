# Tesla Port Monitor v1.8.3

- 신규 관리자 화면: `admin/shanghai_history.php`
- DB에 수집된 자동차운반선 호출부호(중복 제거, 최대 100개)를 API024로 조회
- API024 과거 입항이력 중 실제 전출항지가 Shanghai/上海/상하이인 행만 표시
- 실제 API024 제출번호(MRN), 입항시각, 세관, 선석을 테스트 표본으로 표시
- 과거 이력 검색은 현재 입항건의 Tesla 후보 플래그를 변경하지 않음
- MRN에서 MSN/HBL/cargMtNo를 임의 생성하지 않음
- DB 변경 없음
