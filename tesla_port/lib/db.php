<?php
declare(strict_types=1);
function tp_db(): PDO {
 static $pdo=null; if($pdo) return $pdo;
 $cfg=require dirname(__DIR__).'/config.php'; $d=$cfg['db'];
 $charset=$d['charset']??'utf8mb4';
 $pdo=new PDO("mysql:host={$d['host']};dbname={$d['name']};charset={$charset}",$d['user'],$d['pass'],[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 return $pdo;
}
