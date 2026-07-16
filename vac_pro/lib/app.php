<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
function date_range($start,$end): array {$out=[];$d=new DateTime($start);$last=new DateTime($end);while($d<=$last){$out[]=$d->format('Y-m-d');$d->modify('+1 day');}return $out;}
function working_days($start,$end,$includeWeekend=false): float {$n=0;foreach(date_range($start,$end) as $d){$w=(int)date('N',strtotime($d));if($includeWeekend||$w<6)$n++;}return $n;}
function leave_type(int $id){$s=db()->prepare('SELECT * FROM leave_types WHERE id=? AND is_active=1');$s->execute([$id]);return $s->fetch();}
function get_employee(int $id){$s=db()->prepare('SELECT * FROM employees WHERE id=? AND is_active=1');$s->execute([$id]);return $s->fetch();}
function grant_for_year(array $e,int $year): float {
 $custom=db()->prepare('SELECT granted_days FROM leave_balances WHERE employee_id=? AND leave_year=?');$custom->execute([$e['id'],$year]);$v=$custom->fetchColumn();if($v!==false)return(float)$v;
 $method=setting('grant_method','fiscal');$hire=new DateTime($e['hire_date']);$base=new DateTime($year.'-12-31');$years=max(0,(int)$hire->diff($base)->y);$days=$years<1?11:min(25,15+intdiv(max(0,$years-1),2));
 if($method==='fiscal' && (int)$hire->format('Y')===$year){$days=round(15*((13-(int)$hire->format('n'))/12),2);} return max(0,$days);
}
function used_for_year(int $employeeId,int $year,bool $includePending=false): float {$statuses=$includePending?"('pending','in_approval','approved')":"('approved')";$s=db()->prepare("SELECT COALESCE(SUM(deduct_days),0) FROM leave_requests WHERE employee_id=? AND YEAR(start_date)=? AND status IN $statuses");$s->execute([$employeeId,$year]);return(float)$s->fetchColumn();}
function resolve_approval_line(array $e): ?array {
 $pdo=db();
 if(!empty($e['approval_line_id'])){$s=$pdo->prepare('SELECT * FROM approval_lines WHERE id=? AND is_active=1');$s->execute([$e['approval_line_id']]);$line=$s->fetch();if($line)return$line;}
 if(!empty($e['department_id'])){$s=$pdo->prepare('SELECT * FROM approval_lines WHERE department_id=? AND is_active=1 ORDER BY sort_order,id LIMIT 1');$s->execute([$e['department_id']]);$line=$s->fetch();if($line)return$line;}
 $line=$pdo->query('SELECT * FROM approval_lines WHERE department_id IS NULL AND is_active=1 ORDER BY sort_order,id LIMIT 1')->fetch();
 return $line?:null;
}
function approval_steps_for_employee(array $e): array {
 $line=resolve_approval_line($e);if(!$line)return[];
 $q=db()->prepare('SELECT als.step_order,als.approver_employee_id,e.name,e.position,e.phone,e.alimtalk_opt_in,e.department_id FROM approval_line_steps als JOIN employees e ON e.id=als.approver_employee_id WHERE als.line_id=? AND e.is_active=1 ORDER BY als.step_order');$q->execute([$line['id']]);
 $steps=$q->fetchAll();foreach($steps as &$step)$step['approval_line_id']=$line['id'];return$steps;
}
function request_scope_sql(string $employeeAlias='e'): array {
 require_once __DIR__.'/auth.php';
 if(can_manage_hr())return ['1=1',[]];
 $ids=accessible_department_ids();$ph=implode(',',array_fill(0,count($ids),'?'));
 return ["$employeeAlias.department_id IN ($ph)",$ids];
}
function daily_capacity_check(int $employeeId,string $start,string $end,int $typeId): void {
 $dup=db()->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id=? AND status IN ('pending','in_approval','approved') AND start_date<=? AND end_date>=?");$dup->execute([$employeeId,$end,$start]);if($dup->fetchColumn())throw new RuntimeException('선택 기간에 이미 신청 또는 승인된 내역이 있습니다.');
 $lt=leave_type($typeId);if(!$lt||!$lt['apply_daily_limit'])return;$limit=(int)setting('daily_max_people','0');if($limit<1)return;
 $q=db()->prepare("SELECT COUNT(DISTINCT employee_id) FROM leave_requests WHERE status IN ('pending','in_approval','approved') AND start_date<=? AND end_date>=?");foreach(date_range($start,$end) as $d){$q->execute([$d,$d]);if((int)$q->fetchColumn()>=$limit)throw new RuntimeException($d.'은 최대 신청 인원 '.$limit.'명에 도달했습니다.');}
}

function salary_for_year(int $employeeId,int $year): int { $s=db()->prepare('SELECT monthly_ordinary_wage FROM employee_salary_history WHERE employee_id=? AND apply_year=?');$s->execute([$employeeId,$year]);return (int)($s->fetchColumn()?:0); }
function round_allowance(float $amount,string $mode): int { if($mode==='10')return (int)(floor($amount/10)*10);if($mode==='100')return (int)(floor($amount/100)*100);return (int)round($amount); }
function allowance_values(array $employee,int $year,float $excluded=0,float $adjustment=0): array { $monthly=salary_for_year((int)$employee['id'],$year);$mh=(float)setting('allowance_monthly_hours','209');$dh=(float)setting('allowance_daily_hours','8');$granted=grant_for_year($employee,$year);$used=used_for_year((int)$employee['id'],$year,false);$payable=max(0,$granted-$used-$excluded+$adjustment);$hourly=$mh>0?$monthly/$mh:0;$daily=$hourly*$dh;$amount=round_allowance($daily*$payable,setting('allowance_rounding','1'));return compact('monthly','mh','dh','granted','used','excluded','adjustment','payable','hourly','daily','amount'); }
