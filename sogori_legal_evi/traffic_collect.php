<?php
declare(strict_types=1);
require __DIR__.'/lib/traffic.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
$raw=file_get_contents('php://input'); $d=json_decode($raw?:'',true); if(!is_array($d)){http_response_code(400);exit;}
$bytes=(int)($d['bytes']??0); $resources=is_array($d['resources']??null)?$d['resources']:[];
traffic_collect_resource($bytes,$resources); http_response_code(204);
