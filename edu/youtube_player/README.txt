YouTube 교육영상 재생관 설치 안내

1. Cafe24 서버에 이 폴더 전체를 업로드합니다.
   예: /www/youtube_player/

2. phpMyAdmin에서 install.sql 내용을 실행합니다.
   - yt_settings
   - yt_videos
   테이블이 생성됩니다.

3. config.php 파일을 수정합니다.
   - DB_HOST
   - DB_NAME
   - DB_USER
   - DB_PASS
   - ADMIN_PASSWORD

4. 관리자 접속
   https://도메인/youtube_player/admin/login.php

5. 사용자 화면
   https://도메인/youtube_player/index.php

6. PPT 연결주소
   관리자 > 등록된 영상 > PPT 연결주소 > URL복사 버튼 클릭
   복사한 주소를 PPT 버튼/이미지에 링크로 넣으면 됩니다.

주의
- 브라우저 정책상 소리 있는 자동재생은 차단될 수 있습니다.
- 자동재생 안정성을 높이려면 관리자 환경설정에서 '음소거 자동재생'을 체크하세요.
- 유튜브 영상 제작자가 외부 iframe 재생을 막은 경우 재생되지 않을 수 있습니다.
