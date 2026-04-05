# 교통사고보험금 합의자료 보관 웹앱 (Cafe24 배포용 최종본)

## 포함 기능
- 사건 등록 / 수정 / 검색
- 진료비, 약제비, 교통비 등 지출내역 관리
- 보험사 제안금 / 반제안 / 통화기록 / 합의완료 이력 관리
- 문서 및 사진 다중 업로드
- 사진 썸네일 미리보기
- 문서 다운로드 / 삭제
- CSV 다운로드
- 한글 인쇄용 보고서 출력 → 브라우저에서 **PDF로 저장** 가능

## 폴더 업로드 경로 예시
Cafe24의 웹루트 예시:
- `/home/hosting_users/계정아이디/www/traffic_claim_app_final/`
또는
- `/www/traffic_claim_app_final/`

## 설치 순서
1. zip 압축 해제 후 전체 업로드
2. `config.sample.php`를 참고하여 `config.php` 수정
3. DB에서 `install.sql` 실행
4. `uploads`, `uploads/documents`, `uploads/photos` 폴더 쓰기 권한 확인
5. `login.php` 또는 `index.php` 접속

## config.php 수정 항목
- DB_HOST
- DB_NAME
- DB_USER
- DB_PASS
- APP_PASSWORD
- BASE_URL

### BASE_URL 예시
- 루트 바로 아래 설치: `''`
- 하위폴더 설치: `'/traffic_claim_app_final'`

## PDF 관련 안내
이 버전은 카페24 일반호스팅에서 한글 폰트 임베딩 문제를 피하기 위해,
서버에서 직접 PDF 바이너리를 만드는 대신 **인쇄 전용 HTML 보고서**를 제공합니다.

사용 방법:
1. 사건 상세화면에서 `PDF용 인쇄화면` 클릭
2. 브라우저 인쇄 실행
3. 대상 프린터를 `PDF로 저장` 선택

이 방식은 실사용에서 한글 깨짐이 적고, Cafe24에서도 배포가 쉽습니다.

## 권장 추가기능(향후)
- 병원별/월별 통원 요약표 자동 생성
- 손해항목별 산정 메모
- 합의서 초안 보관
- 사건별 백업 zip 다운로드
