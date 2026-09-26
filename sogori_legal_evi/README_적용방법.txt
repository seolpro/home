소고리 현장증거 웹페이지 - 트래픽 절감 최소 패치

[기준]
사용자가 직접 수정한 최신 index.php를 기준으로 패치했습니다.
기존 문구/모달/전화연결/사진 확대 기능은 유지합니다.

[파일]
1. index.php             교체
2. print.php             교체
3. make_web_images.php   신규
4. .htaccess             신규 또는 기존 설정에 병합

[중요 - 보존]
- uploads/의 기존 원본사진: 수정/삭제/이동하지 않음
- data/evidence.json: 수정하지 않음
- config.php: 수정하지 않음
- admin/: 수정하지 않음

[적용 순서]
1) 현재 서버 index.php, print.php를 백업
2) 새 index.php, print.php 업로드
3) make_web_images.php 업로드
4) 브라우저에서 /make_web_images.php 접속 → '웹용 이미지 일괄 생성'
5) uploads/web/ 폴더와 경량 이미지 생성 확인
6) index.php와 print.php에서 사진 표시 확인
7) 필요하면 make_web_images.php 삭제
8) .htaccess는 기존 파일이 없으면 업로드. 기존 .htaccess가 있으면 내용을 병합하는 것이 안전합니다.

[동작]
- 웹용 이미지가 있으면 index/확대/인쇄에서 자동 사용
- 웹용 이미지가 아직 없으면 기존 원본으로 자동 fallback
- evidence.json의 기존 파일경로는 변경하지 않음
- 새 사진 등록 후 make_web_images.php를 다시 실행하면 새 웹용 이미지만 생성
