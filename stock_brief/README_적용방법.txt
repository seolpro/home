주식 브리핑 V1.3 - 문자 호환/종목수 확대 패치

[교체 파일]
1. stock_brief/lib.php
2. stock_brief/api/morning_brief.php
3. stock_brief/admin/index.php

[DB 수정]
없음

[문자 실패 개선]
- 이모지 제거
- 장식 특수문자 일반문자 치환
- EUC-KR 표현 불가문자 제거
- 뉴스 제목도 발송 전 안전문자로 정리
- 긴 브리핑은 LMS 여러 건으로 자동 분할

[종목 등록]
- 등록 개수 제한 없음
- 관리자 화면에 전체 등록수/사용 종목수 표시
- 4개를 넘어 계속 등록 가능
- 종목이 많으면 문자만 자동으로 여러 건 분할

[테스트]
파일 교체 후 관리자 화면의
"지금 테스트 문자 보내기"를 실행하세요.

반환 JSON에서
"stock_count": 등록 종목 수
"message_parts": 실제 LMS 분할 건수
"results": 각 문자 발송 결과
를 확인할 수 있습니다.
