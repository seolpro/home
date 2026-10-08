# v1.9.3.4

기본형(텍스트형) 카카오 알림톡 발송 수정 패치입니다.

- `/v1/kakao` payload의 `messageType`을 `ALI` → `ALT`로 수정
- `senderProfile`, `templateCode`, `duplicateFlag`, `targetCount`, `targets`, `isResend=N`, `refKey` 유지
- 대체문자 미사용이므로 최상위 `from` 및 `resend` 없음
- 테스트 페이지에서 불필요한 `PPURIO_SENDER_PHONE` 필수검사 제거
- 운영 `PPURIO_ENABLED=false` 및 DB/notified_at 안전정책 유지

근거: 기본형 알림톡 연동 성공 사례의 뿌리오 `/v1/kakao` 요청은 `messageType: ALT`를 사용합니다.
