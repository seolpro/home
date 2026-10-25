<?php
declare(strict_types=1);
const APP_SETUP_KEY = '4d18e4d50863ac9f9e24f39a602feae021f747e056314791219f28686c8a51a5';
const APP_BASE_URL = 'https://seolhopro.mycafe24.com/congrate_app';
const APP_TIMEZONE = 'Asia/Seoul';
date_default_timezone_set(APP_TIMEZONE);
// Common Cafe24 DB config returns host,name,user,pass,charset.
$dbconf = require dirname(__DIR__).'/private_config/db_common.php';
function db(): PDO { global $dbconf; static $pdo; if (!$pdo) { $dsn='mysql:host='.$dbconf['host'].';dbname='.$dbconf['name'].';charset='.($dbconf['charset']??'utf8mb4'); $pdo=new PDO($dsn,$dbconf['user'],$dbconf['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); }return $pdo; }
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrf():string {return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
function check_csrf():void {if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))throw new RuntimeException('보안토큰 오류');}
function auth():void {if(empty($_SESSION['admin'])){header('Location: login.php');exit;}}
function redirect($url='index.php'):void{header('Location: '.$url);exit;}
function money($v):int { $v=preg_replace('/[^0-9]/u','',(string)$v);return (int)$v; }
function phone($v):string {return preg_replace('/\D/','',(string)$v);}
