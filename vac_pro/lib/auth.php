<?php
require_once __DIR__.'/db.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
function admin_user(){return $_SESSION['admin']??null;}
function require_admin(): void { if(!admin_user()){header('Location: login.php');exit;} }
function has_role(array $roles): bool { $u=admin_user(); return $u && in_array($u['role'],$roles,true); }
function is_super_admin(): bool { return has_role(['super_admin']); }
function can_manage_hr(): bool { return has_role(['super_admin','hr_admin']); }
function admin_employee_id(): int { return (int)(admin_user()['employee_id']??0); }
function accessible_department_ids(): array {
  static $ids=null; if($ids!==null)return $ids;
  if(has_role(['super_admin','hr_admin'])) return $ids=[];
  $u=admin_user(); if(!$u)return $ids=[-1];
  $q=db()->prepare('SELECT department_id FROM admin_department_scopes WHERE admin_id=?');$q->execute([$u['id']]);
  $ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
  if(!$ids && !empty($u['employee_id'])){$q=db()->prepare('SELECT department_id FROM employees WHERE id=?');$q->execute([$u['employee_id']]);$d=(int)$q->fetchColumn();if($d)$ids=[$d];}
  return $ids?:[-1];
}
function require_hr_admin(): void { if(!can_manage_hr()){http_response_code(403);die('접근 권한이 없습니다.');} }
function can_view_department(int $departmentId): bool { if(can_manage_hr())return true; return in_array($departmentId,accessible_department_ids(),true); }
