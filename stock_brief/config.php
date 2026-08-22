<?php
return [
    'app_name' => '주식 포트폴리오 아침 브리핑',
    'base_url' => 'https://seolhopor.mycafe24.com/stock_brief',
    'timezone' => 'Asia/Seoul',
    'db' => ['host'=>'localhost','port'=>3306,'name'=>'seolhopro','user'=>'seolhopro','pass'=>'ajou2130--','charset'=>'utf8mb4'],
    'admin' => ['id'=>'admin','password_hash'=>'$2y$12$Yzg0.lI2EbNss20lHIs1J.4EEWUN4KoMZXK0Xh6AQdD7PZLitnMcC'],
    'security' => ['gas_key'=>'https://script.google.com/macros/s/AKfycbx6bBVIM2GUgVUN8h8UR9i4JoqMY8mcVKExpZ-VoeqtQt5xJL43NQ2w8nYL-greBxdhuQ/exec'],
    'sms' => ['enabled'=>true,'provider'=>'ppurio','account'=>'aj9770','auth_key'=>'08868d27d42a13b10954f7c9705063152e03d948b824bf336ff611be225957b9','sender'=>'01071186639'],
    'brief' => ['recipient_name'=>'관리자','recipient_phone'=>'01071186639','include_news'=>true,'max_news_per_stock'=>1],
];
