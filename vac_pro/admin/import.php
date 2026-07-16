<?php
require_once __DIR__.'/../lib/app.php';
require_once __DIR__.'/../lib/auth.php';
require_hr_admin();
$pageTitle='일괄업로드';
include '_top.php';
$pdo=db();
$msg='';$err='';$details=[];

function csv_to_utf8(string $raw): string {
    if(substr($raw,0,3)==="\xEF\xBB\xBF") return substr($raw,3);
    if(function_exists('mb_detect_encoding')){
        $enc=mb_detect_encoding($raw,['UTF-8','CP949','EUC-KR'],true);
        if($enc && $enc!=='UTF-8') return mb_convert_encoding($raw,'UTF-8',$enc);
    }
    return $raw;
}
function csv_rows(array $file): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('CSV 파일을 선택하세요.');
    if(($file['size']??0)>5*1024*1024) throw new RuntimeException('파일은 5MB 이하만 업로드할 수 있습니다.');
    $raw=file_get_contents($file['tmp_name']);
    if($raw===false) throw new RuntimeException('업로드 파일을 읽을 수 없습니다.');
    $raw=csv_to_utf8($raw);
    $fp=fopen('php://temp','r+');fwrite($fp,$raw);rewind($fp);
    $rows=[];
    while(($row=fgetcsv($fp))!==false){
        $row=array_map(static fn($v)=>trim((string)$v),$row);
        if(count(array_filter($row,static fn($v)=>$v!==''))===0) continue;
        $rows[]=$row;
    }
    fclose($fp);
    if(count($rows)<2) throw new RuntimeException('헤더와 데이터가 포함된 CSV 파일을 사용하세요.');
    $headers=array_map(static fn($v)=>preg_replace('/\s+/u','',trim($v)),array_shift($rows));
    $out=[];
    foreach($rows as $i=>$row){
        $item=['__row'=>$i+2];
        foreach($headers as $k=>$h)$item[$h]=$row[$k]??'';
        $out[]=$item;
    }
    return $out;
}
function v(array $row,array $keys,string $default=''): string {foreach($keys as $k)if(array_key_exists($k,$row)&&$row[$k]!=='')return trim((string)$row[$k]);return $default;}
function bool_csv(string $v,bool $default=true): int {if($v==='')return $default?1:0;$normalized=function_exists('mb_strtolower')?mb_strtolower(trim($v),'UTF-8'):strtolower(trim($v));return in_array($normalized,['1','y','yes','true','사용','수신','재직','대상'],true)?1:0;}
function csv_date(string $v): ?string {if($v==='')return null;$v=str_replace(['.','/'],'-',$v);$ts=strtotime($v);return $ts?date('Y-m-d',$ts):null;}
function find_department_id(PDO $pdo,string $name): ?int {if($name==='')return null;$s=$pdo->prepare('SELECT id FROM departments WHERE name=? LIMIT 1');$s->execute([$name]);$id=$s->fetchColumn();if($id)return(int)$id;$s=$pdo->prepare('INSERT INTO departments(name,is_active) VALUES(?,1)');$s->execute([$name]);return(int)$pdo->lastInsertId();}
function find_line_id(PDO $pdo,string $name): ?int {if($name==='')return null;$s=$pdo->prepare('SELECT id FROM approval_lines WHERE name=? AND is_active=1 LIMIT 1');$s->execute([$name]);$id=$s->fetchColumn();return$id?(int)$id:null;}
function find_employee(PDO $pdo,string $no,string $name): ?array {$s=null;if($no!==''){$s=$pdo->prepare('SELECT * FROM employees WHERE employee_no=? LIMIT 1');$s->execute([$no]);$e=$s->fetch();if($e)return$e;}if($name!==''){$s=$pdo->prepare('SELECT * FROM employees WHERE name=? AND is_active=1 ORDER BY id LIMIT 1');$s->execute([$name]);$e=$s->fetch();if($e)return$e;}return null;}
function find_leave_type(PDO $pdo,string $code,string $name): ?array {if($code!==''){$s=$pdo->prepare('SELECT * FROM leave_types WHERE code=? LIMIT 1');$s->execute([$code]);$r=$s->fetch();if($r)return$r;}if($name!==''){$s=$pdo->prepare('SELECT * FROM leave_types WHERE name=? LIMIT 1');$s->execute([$name]);$r=$s->fetch();if($r)return$r;}return null;}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $type=(string)($_POST['import_type']??'');
    try{
        $rows=csv_rows($_FILES['csv_file']??[]);
        $batch=$pdo->prepare('INSERT INTO import_batches(import_type,original_filename,total_rows,created_by) VALUES(?,?,?,?)');
        $batch->execute([$type,basename((string)($_FILES['csv_file']['name']??'')),count($rows),admin_user()['id']??null]);
        $batchId=(int)$pdo->lastInsertId();$ok=0;$fail=0;

        foreach($rows as $row){
            try{
                $pdo->beginTransaction();
                if($type==='employees'){
                    $no=v($row,['사번','employee_no']);$name=v($row,['성명','이름','name']);
                    if($name==='')throw new RuntimeException('성명이 없습니다.');
                    $departmentId=find_department_id($pdo,v($row,['부서','부서명','department']));
                    $lineName=v($row,['결재선','결재선명','approval_line']);
                    $lineId=find_line_id($pdo,$lineName);
                    if($lineName!==''&&!$lineId)throw new RuntimeException('결재선 "'.$lineName.'"을 찾을 수 없습니다.');
                    $hire=csv_date(v($row,['입사일','hire_date']));if(!$hire)throw new RuntimeException('입사일 형식이 올바르지 않습니다.');
                    $existing=find_employee($pdo,$no,$name);
                    $data=[
                        $no?:null,$name,v($row,['직책','position']),$departmentId,v($row,['휴대폰','연락처','phone']),v($row,['이메일','email']),$hire,
                        csv_date(v($row,['퇴사일','quit_date'])),v($row,['개별부여일수','부여일수','custom_grant_days'])!==''?(float)v($row,['개별부여일수','부여일수','custom_grant_days']):null,
                        v($row,['의무사용률','mandatory_rate'])!==''?(float)v($row,['의무사용률','mandatory_rate']):null,$lineId,
                        bool_csv(v($row,['연차수당대상','allowance_enabled']),true),bool_csv(v($row,['알림톡수신','alimtalk_opt_in']),true),(int)v($row,['표시순서','sort_order'],'0'),bool_csv(v($row,['재직상태','사용여부','is_active']),true),v($row,['비고','memo'])
                    ];
                    if($existing){
                        $sql='UPDATE employees SET employee_no=?,name=?,position=?,department_id=?,phone=?,email=?,hire_date=?,quit_date=?,custom_grant_days=?,mandatory_rate=?,approval_line_id=?,allowance_enabled=?,alimtalk_opt_in=?,sort_order=?,is_active=?,memo=? WHERE id=?';
                        $data[]=$existing['id'];$pdo->prepare($sql)->execute($data);
                    }else{
                        $sql='INSERT INTO employees(employee_no,name,position,department_id,phone,email,hire_date,quit_date,custom_grant_days,mandatory_rate,approval_line_id,allowance_enabled,alimtalk_opt_in,sort_order,is_active,memo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                        $pdo->prepare($sql)->execute($data);
                    }
                }elseif($type==='approved_leave'){
                    $no=v($row,['사번','employee_no']);$name=v($row,['성명','이름','name']);$emp=find_employee($pdo,$no,$name);if(!$emp)throw new RuntimeException('직원을 찾을 수 없습니다.');
                    $lt=find_leave_type($pdo,v($row,['휴가코드','leave_code']),v($row,['휴가종류','휴가유형','leave_type']));if(!$lt)throw new RuntimeException('휴가유형을 찾을 수 없습니다.');
                    $start=csv_date(v($row,['시작일','start_date']));$end=csv_date(v($row,['종료일','end_date']));if(!$start)throw new RuntimeException('시작일이 올바르지 않습니다.');if(!$end)$end=$start;if($end<$start)throw new RuntimeException('종료일이 시작일보다 빠릅니다.');
                    $days=(float)v($row,['사용일수','일수','days'],'0');if($days<=0)throw new RuntimeException('사용일수는 0보다 커야 합니다.');
                    $deductRaw=v($row,['차감일수','deduct_days']);$deduct=$deductRaw!==''?(float)$deductRaw:($lt['deduct_enabled']?$days:0);
                    $dup=$pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id=? AND leave_type_id=? AND start_date=? AND end_date=? AND requested_days=? AND status='approved'");$dup->execute([$emp['id'],$lt['id'],$start,$end,$days]);if((int)$dup->fetchColumn()>0)throw new RuntimeException('동일한 승인내역이 이미 존재합니다.');
                    $s=$pdo->prepare("INSERT INTO leave_requests(employee_id,leave_type_id,approval_line_id,start_date,end_date,requested_days,deduct_days,half_option,memo,status,current_step,approved_at,source_type,import_batch_id) VALUES(?,?,?,?,?,?,?,?,?,'approved',0,?,'import',?)");
                    $s->execute([$emp['id'],$lt['id'],null,$start,$end,$days,$deduct,v($row,['오전오후','half_option']),v($row,['비고','사유','memo']),csv_date(v($row,['승인일','approved_at']))?:$end,$batchId]);
                }else throw new RuntimeException('업로드 종류를 선택하세요.');
                $pdo->commit();$ok++;
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$fail++;$details[]=$row['__row'].'행: '.$e->getMessage();}
        }
        $summary='성공 '.$ok.'건 / 실패 '.$fail.'건';
        $pdo->prepare('UPDATE import_batches SET success_rows=?,failed_rows=?,result_message=? WHERE id=?')->execute([$ok,$fail,implode("\n",$details),$batchId]);
        $msg=$summary;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$err=$e->getMessage();}
}
$history=$pdo->query("SELECT b.*,a.name admin_name FROM import_batches b LEFT JOIN admins a ON a.id=b.created_by ORDER BY b.id DESC LIMIT 20")->fetchAll();
?>
<div class="page-title-row"><div><h1>직원·기승인 휴가 일괄업로드</h1><p class="page-desc">연도 중간에 시스템을 도입할 때 기존 직원자료와 이미 승인된 휴가 사용내역을 CSV로 한 번에 등록합니다.</p></div></div>
<?php if($msg):?><div class="alert ok"><?=h($msg)?></div><?php endif?><?php if($err):?><div class="alert err"><?=h($err)?></div><?php endif?>
<?php if($details):?><div class="alert err"><strong>실패내역</strong><br><?=nl2br(h(implode("\n",array_slice($details,0,100))))?></div><?php endif?>
<div class="grid">
<section class="col6 card"><h2>CSV 업로드</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><label>자료 종류<select name="import_type" required><option value="">선택하세요</option><option value="employees">직원 일괄등록·수정</option><option value="approved_leave">기승인 휴가 사용내역</option></select></label><label>CSV 파일<input type="file" name="csv_file" accept=".csv,text/csv" required></label><button class="btn">업로드 및 검증</button></form><p class="muted">UTF-8 및 한글 Windows CSV(CP949/EUC-KR)를 지원합니다. 직원은 사번 우선, 사번이 없으면 성명으로 기존 자료를 찾아 수정합니다.</p></section>
<section class="col6 card"><h2>샘플 양식</h2><p><a class="btn" href="../samples/employees_import_sample.csv" download>직원 샘플 CSV</a></p><p><a class="btn secondary" href="../samples/approved_leave_import_sample.csv" download>기승인 휴가 샘플 CSV</a></p><div class="muted">기승인 휴가는 결재과정을 새로 만들지 않고 승인완료 상태로 등록되며, 연차 사용량·잔여일수·연차수당 계산에 즉시 반영됩니다.</div></section>
</div>
<div class="card"><h2>최근 업로드 이력</h2><div class="table-wrap"><table class="table"><tr><th>일시</th><th>종류</th><th>파일</th><th>전체</th><th>성공</th><th>실패</th><th>실행자</th></tr><?php foreach($history as $h):?><tr><td><?=h($h['created_at'])?></td><td><?=h($h['import_type']==='employees'?'직원':'기승인 휴가')?></td><td><?=h($h['original_filename'])?></td><td><?=$h['total_rows']?></td><td><?=$h['success_rows']?></td><td><?=$h['failed_rows']?></td><td><?=h($h['admin_name']?:'-')?></td></tr><?php endforeach?></table></div></div>
<?php include '_bottom.php';?>
