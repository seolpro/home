[DB 방식 트래픽 패치]

1) 기존 config.php는 덮어쓰지 마세요.
   CONFIG_ADD_ONLY.txt 내용을 기존 config.php에 추가하고 DB/SMS 값을 입력합니다.

2) 새로 추가/덮어쓰기
   - lib/traffic.php
   - assets/traffic.js
   - traffic_collect.php
   - admin/traffic.php
   - install_traffic.php
   - index.php
   - print.php
   - admin/index.php

3) 브라우저에서 /sogori_legal_evi/install_traffic.php 를 1회 실행합니다.
   생성 테이블:
   - traffic_visits
   - traffic_resource_events
   - traffic_alert_logs

4) 설치 성공 후 install_traffic.php는 삭제하거나 이름을 변경하세요.

5) 관리자 > 방문·트래픽 통계에서 기록을 확인합니다.

6) 정상 기록 확인 후 config.php의 TRAFFIC_SMS_ENABLED를 true로 바꾸면 80%/90% 문자 경보가 활성화됩니다.

주의: 트래픽 수치는 브라우저 Resource Timing 기반 자체 관측치이며 Cafe24 계정 전체 실제 차감 트래픽과 완전히 동일하지 않을 수 있습니다.
