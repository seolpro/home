-- HRM Plus: 최종승인 전 직원 알림톡 안내 패치
-- 기존 운영 DB에서 1회 실행합니다. 테이블 구조 변경은 없습니다.

INSERT INTO notification_templates
(event_code,event_name,template_code,message_template,button_json,variable_help,is_active,sort_order)
VALUES
('approved_all_staff','최종승인 전 직원 휴가 안내','',
 '#{var1}님의 휴가가 승인되었습니다.\n휴가종류: #{var2}\n기간: #{var3} ~ #{var4}\n사용일수: #{var5}일\n업무에 참고하시기 바랍니다.',
 '',
 'var1=신청직원명/직책, var2=휴가종류, var3=시작일, var4=종료일, var5=사용일수',
 0,35)
ON DUPLICATE KEY UPDATE
 event_name=VALUES(event_name),
 variable_help=VALUES(variable_help);
