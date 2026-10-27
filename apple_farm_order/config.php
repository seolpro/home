<?php
declare(strict_types=1);
return [
 'site_name'=>'진이네사과농장 직거래 주문',
 'db_config_path'=>dirname(__DIR__).'/private_config/db_common.php',
 'admin_password_hash'=>'$2y$12$tip3bsobe4ucX/kPcsqX1O4n6yLPq/vyKwoRiPdsvOG2Uepkz0ixe', // php password_hash() 결과 입력
 'kakao_js_key'=>'', // 카카오 JavaScript 키 (선택: 주소검색은 다음 우편번호 서비스 사용)
 'products'=>[
  'apple_5kg'=>['name'=>'가정용 양광 7kg','price'=>0],
  'kamhong_5kg'=>['name'=>'가정용 감홍 5kg','price'=>0],  
  'kamhong_10kg'=>['name'=>'가정용 감홍 10kg','price'=>0],
  'gift_5kg'=>['name'=>'선물용 감홍 5kg','price'=>0],
  'gift_10kg'=>['name'=>'선물용 감홍 10kg','price'=>0],
 ],
 'payment_account'=>[
  'bank'=>'별도안내',
  'number'=>'',
  'holder'=>'',
 ],
 'max_quantity'=>50,
 'order_alimtalk'=>[
  'enabled'=>true,
  'account'=>'aj9770',
  'auth_key'=>'08868d27d42a13b10954f7c9705063152e03d948b824bf336ff611be225957b9',
  'sender_profile'=>'@타운카김설호',
  'template_code'=>'ppur_2026102719295702342550815',
  'recipients'=>[
    '01071186639',
    '01050640027', // 장유진 진이네농장주
    '01049037057', // 김영진
  ],
],
];
