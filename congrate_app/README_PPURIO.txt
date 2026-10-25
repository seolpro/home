뿌리오 문자 발송 패치
1. 서버의 congrate_app/messages.php 파일을 교체하고 ppurio.php, ppurio_config.php를 추가합니다.
2. ppurio_config.php에 enabled=true, account, auth_key, sender(사전 등록 발신번호)를 입력합니다.
3. 관리자 로그인 > 경조사 안내 > 수신자 선택 > 발송 전 검토 > 실제 문자 발송.
4. 기존 DB 변경 불필요. cg_message_logs에 접수/실패 기록.
5. accepted는 API 발송 요청 접수이며 실제 수신 성공을 보증하지 않습니다.
6. 이 패치는 문자 SMS/LMS만 지원하며 카카오 알림톡 템플릿 연동은 포함하지 않습니다.
7. 발송 전 수신 동의 및 전화번호 확인. 소량 테스트 후 사용.
8. ppurio_config.php는 민감정보가 포함되므로 접근 제한 및 백업 관리가 필요합니다.
