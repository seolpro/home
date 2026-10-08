<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function tp_admin_required(){session_start(); if(empty($_SESSION['tp_admin'])){header('Location:login.php');exit;}}
function tp_port_code(): string { if(defined('PORT_CODE')) return (string)PORT_CODE; if(defined('PORT_AUTHORITY_CODE')) return (string)PORT_AUTHORITY_CODE; return '031'; }
function tp_lookback_days(): int { return defined('LOOKBACK_DAYS') ? (int)LOOKBACK_DAYS : 3; }
function tp_lookahead_days(): int { return defined('LOOKAHEAD_DAYS') ? (int)LOOKAHEAD_DAYS : 14; }
function tp_model_guess(?int $qty, ?float $kg): string { if(!$qty||!$kg)return '미확인';$avg=$kg/$qty;if($avg>=1650&&$avg<2050)return 'Model Y 추정';if($avg>=1450&&$avg<1850)return 'Model 3 추정';return 'Tesla 차량(모델 판정 보류)'; }
function tp_uid(array $r): string {$stable=[$r['port_code']??'', $r['call_sign']??'', $r['voyage_no']??''];if(implode('',$stable)==='')$stable=[$r['vessel_name']??'', $r['eta']??'', $r['port_name']??''];return hash('sha256',implode('|',$stable));}
function tp_window(): array {return [date('Y-m-d',strtotime('-'.tp_lookback_days().' days')),date('Y-m-d',strtotime('+'.tp_lookahead_days().' days'))];}
function tp_is_vehicle_carrier(array $r): bool { return mb_strpos((string)($r['vessel_kind']??''),'자동차운반선')!==false; }
function tp_is_shanghai_origin(array $r): bool {
    $s=mb_strtoupper(trim((string)(($r['origin_port']??'').' '.($r['first_origin_port']??'').' '.($r['origin_port_code']??''))),'UTF-8');
    if($s==='') return false;
    foreach(['SHANGHAI','上海','상하이'] as $k){ if(mb_strpos($s,mb_strtoupper($k,'UTF-8'))!==false) return true; }
    return false;
}
// 하위호환: 기존 호출부는 유지하되 의미는 '상하이 출항'으로 제한
function tp_is_china_origin(array $r): bool { return tp_is_shanghai_origin($r); }
