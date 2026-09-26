소고리 현장증거 웹페이지 v1.2 - 인쇄/PDF 전체사진 펼침 패치

[교체/추가 파일]
1. print.php  → 서버 웹앱 최상위 폴더에 새로 업로드
2. index.php  → 기존 index.php를 교체

[보존되는 기존 자료]
- uploads/ : 절대 삭제/교체하지 않음
- data/evidence.json : 절대 삭제/교체하지 않음
- config.php : 교체하지 않음
- admin/ : 교체하지 않음

[변경 내용]
- index.php의 '인쇄 / PDF' 버튼이 print.php를 새 창으로 엽니다.
- print.php는 등록된 사진을 SPOT별로 전부 펼쳐 표시합니다.
- 각 사진 아래에 촬영일시, 촬영방향, 현장관찰, 입증취지, SHA-256을 표시합니다.
- 브라우저의 '인쇄 / PDF 저장' 기능으로 A4 PDF 생성이 가능합니다.

[권장 적용 순서]
기존 index.php 백업 → 새 index.php 덮어쓰기 → print.php 업로드 → index에서 인쇄/PDF 버튼 테스트
