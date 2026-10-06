# Tesla Port v1.9.3.2

알림톡 실발송 payload 보완 패치입니다.

- `senderProfile` 유지
- Bizppurio `/v1/kakao` 필수 `isResend=false` 추가
- 문자 대체발송은 사용하지 않음
- 테스트 발송은 운영 `PPURIO_ENABLED=false`를 우회하지만 운영 DB와 `notified_at`은 변경하지 않음
- 기존 account/messageType/from/content/targetCount/targets/changeWord/refKey/templateCode 구조 유지
- SQL 변경 없음
