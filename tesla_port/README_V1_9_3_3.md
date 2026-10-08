# v1.9.3.3

- 사용자 제공 뿌리오 공식 v1/kakao 가이드 구조 반영
- messageType: `ALI`
- 최상위 `from`, `content` 제거
- `senderProfile`, `templateCode`, `duplicateFlag`, `targetCount`, `targets`, `refKey` 유지
- 알림톡 전용 테스트이므로 `isResend`: `N`
- `resend` 객체 미전송
- 테스트 페이지는 운영 DB 및 notified_at을 변경하지 않음
