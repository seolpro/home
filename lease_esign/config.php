<?php
return [
 'app_name'=>'온라인 주거용 임대차계약',
 'base_url'=>'https://seolhopro.mycafe24.com/lease_esign',
 'timezone'=>'Asia/Seoul',
 'db'=>['host'=>'localhost','port'=>3306,'name'=>'seolhopro','user'=>'seolhopro','pass'=>'ajou2130--','charset'=>'utf8mb4'],
 'admin'=>['id'=>'admin','password_hash'=>''],
 'security'=>['app_key'=>'CHANGE_TO_RANDOM_64_CHARACTERS','token_days'=>14],
 'sms'=>[
   'enabled'=>false,'provider'=>'ppurio','account'=>'aj9770','auth_key'=>'','sender'=>'',
   'admin_phone'=>'','callback_url'=>'',
 ],
];
