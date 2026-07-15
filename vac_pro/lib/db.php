<?php
if (!file_exists(__DIR__.'/../config.php')) { die('config.php가 없습니다. config.sample.php를 복사해 설정하세요.'); }
require_once __DIR__.'/../config.php';
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Seoul');
function db(): PDO { static $pdo; if($pdo) return $pdo; $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); return $pdo; }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function setting(string $key,$default=''){ try{$s=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:$v;}catch(Throwable $e){return $default;} }
function set_setting(string $key,$value): void { $s=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$s->execute([$key,(string)$value]); }
function csrf_token(): string { if(session_status()!==PHP_SESSION_ACTIVE)session_start(); if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function verify_csrf(): void { if(session_status()!==PHP_SESSION_ACTIVE)session_start(); if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??''))throw new RuntimeException('잘못된 요청입니다.'); }
function fmt_days($v): string { $n=(float)$v; return abs($n-round($n))<.00001?(string)(int)round($n):rtrim(rtrim(number_format($n,2,'.',''),'0'),'.'); }
