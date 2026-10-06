# Tesla Port v1.9.3.1

알림톡 발송 payload 수정 패치입니다.

- Bizppurio `/v1/kakao` 요청의 발신 프로필 필드를 `senderKey`에서 필수 필드 `senderProfile`로 수정
- 값은 기존 `PPURIO_SENDER_PROFILE` 설정을 그대로 사용
- 테스트 페이지 버전 표시를 v1.9.3.1로 변경
- 운영 `PPURIO_ENABLED=false` 유지 가능: 관리자 테스트 페이지만 force 발송
- 테스트 발송은 `notified_at` 및 Tesla 입항 DB를 변경하지 않음
- DB SQL 변경 없음
