<?php
/* stock_brief v2 - 별도 운영용 설정 */
return [
    'app_name'=>'주식 포트폴리오 아침 브리핑',
    'base_url'=>'https://seolhopro.mycafe24.com/stock_brief_web',
    'timezone'=>'Asia/Seoul',
    'db'=>[
        'host'=>'localhost','port'=>3306,'name'=>'seolhopro','user'=>'seolhopro','pass'=>'ajou2130--','charset'=>'utf8mb4'
    ],
    'admin'=>['id'=>'admin','password_hash'=>'$2y$12$0fQ80gz.OfTghGbnLVeCCe/hPSs7QTaxcgehDR3U.RM1Y1jHDAtca'],
    'security'=>['cron_key'=>'AjouStock_2026_x7K9mQ2pL8vN4sR6cT1w'],
    // 기존 SMS 기능은 v2 자동발송에서 사용하지 않지만 계정정보 공용값으로 둘 수 있습니다.
    'sms'=>['enabled'=>true,'provider'=>'ppurio','account'=>'seolhopro','auth_key'=>'08868d27d42a13b10954f7c9705063152e03d948b824bf336ff611be225957b9','sender'=>'01071186639'],
    'alimtalk'=>[
        'enabled'=>true,
        'account'=>'aj9770',
        'auth_key'=>'08868d27d42a13b10954f7c9705063152e03d948b824bf336ff611be225957b9',
        'sender'=>'01071186639',
        'sender_profile'=>'@타운카김설호',
        'template_code'=>'ppur_2026100206325647407609246',
        // 승인받은 템플릿 본문과 글자/줄바꿈까지 동일하게 입력
        'content'=>"[주식시황 데일리 브리핑]\n서비스 신청에 따라. 아래와 같이 주식시황 브리핑을 보내 드립니다\n주식시장 브리핑이 업데이트되었습니다.\n\n아래 버튼을 눌러 상세 내용을 확인해 주세요"
    ],
    'brief'=>['recipient_name'=>'관리자','recipient_phone'=>'01071186639','include_news'=>true,'max_news_per_stock'=>1],
];
