<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require_once dirname(__DIR__).'/lib.php';
try{
    $r=run_daily_brief(false);
    echo '['.date('Y-m-d H:i:s').'] '.json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(!empty($r['ok'])?0:1);
}catch(Throwable $e){fwrite(STDERR,'['.date('Y-m-d H:i:s').'] ERROR '.$e->getMessage().PHP_EOL);exit(1);}
