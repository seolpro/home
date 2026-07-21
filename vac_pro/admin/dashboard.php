<?php
require_once __DIR__.'/../lib/app.php';
$pageTitle='대시보드';
include '_top.php';
$pdo=db();

[$scopeSql,$scopeParams]=request_scope_sql('e');
$q=trim($_GET['q']??'');
$year=(int)($_GET['year']??date('Y'));
if($year<2000 || $year>2100)$year=(int)date('Y');

$params=$scopeParams;
$search='';
if($q!==''){
    $search=' AND (e.name LIKE ? OR e.employee_no LIKE ? OR d.name LIKE ? OR lt.name LIKE ? OR lr.start_date LIKE ?)';
    for($i=0;$i<5;$i++)$params[]='%'.$q.'%';
}

function scalarScoped($sql,$params){
    $s=db()->prepare($sql);
    $s->execute($params);
    return $s->fetchColumn();
}

$stats=[
    '직원'=>scalarScoped("SELECT COUNT(*) FROM employees e WHERE e.is_active=1 AND $scopeSql",$scopeParams),
    '승인대기'=>scalarScoped("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.status IN ('pending','in_approval') AND $scopeSql",$scopeParams),
    '오늘휴가'=>scalarScoped("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id WHERE lr.status='approved' AND CURDATE() BETWEEN lr.start_date AND lr.end_date AND $scopeSql",$scopeParams),
    '내 결재대기'=>0
];
if(admin_employee_id()){
    $s=$pdo->prepare("SELECT COUNT(*) FROM request_approvals WHERE approver_employee_id=? AND status='pending'");
    $s->execute([admin_employee_id()]);
    $stats['내 결재대기']=$s->fetchColumn();
}

/* 최근 신청 */
$sql="SELECT lr.*,e.name,e.position,e.employee_no,d.name department_name,lt.name leave_name
      FROM leave_requests lr
      JOIN employees e ON e.id=lr.employee_id
      LEFT JOIN departments d ON d.id=e.department_id
      JOIN leave_types lt ON lt.id=lr.leave_type_id
      WHERE $scopeSql $search
      ORDER BY lr.id DESC LIMIT 100";
$s=$pdo->prepare($sql);
$s->execute($params);
$recent=$s->fetchAll();

/* 직원별 의무사용 현황 */
$mandatoryParams=$scopeParams;
$mandatorySearch='';
if($q!==''){
    $mandatorySearch=' AND (e.name LIKE ? OR e.employee_no LIKE ? OR d.name LIKE ?)';
    for($i=0;$i<3;$i++)$mandatoryParams[]='%'.$q.'%';
}
$es=$pdo->prepare("SELECT e.*,d.name department_name
                   FROM employees e
                   LEFT JOIN departments d ON d.id=e.department_id
                   WHERE e.is_active=1 AND $scopeSql $mandatorySearch
                   ORDER BY COALESCE(d.sort_order,999999),d.id,e.sort_order,e.id");
$es->execute($mandatoryParams);
$employees=$es->fetchAll();

$mandatoryRows=[];
$totalUsageRate=0.0;
$achievedCount=0;
foreach($employees as $employee){
    $granted=grant_for_year($employee,$year);
    $approved=used_for_year((int)$employee['id'],$year,false);
    $approvedAndPending=used_for_year((int)$employee['id'],$year,true);
    $pending=max(0,$approvedAndPending-$approved);
    $remaining=$granted-$approved;
    $mandatoryRate=$employee['mandatory_rate']!==null
    ? (float)$employee['mandatory_rate']
    : (float)setting('mandatory_rate','70');
    $mandatoryDays=$granted*$mandatoryRate/100;
    $usageRate=$granted>0?round($approved/$granted*100,1):0;
    $shortage=max(0,$mandatoryDays-$approved);
    $achieved=$approved+0.00001>=$mandatoryDays;
    if($achieved)$achievedCount++;
    $totalUsageRate+=$usageRate;
    $mandatoryRows[]=[
        'employee'=>$employee,
        'granted'=>$granted,
        'approved'=>$approved,
        'pending'=>$pending,
        'remaining'=>$remaining,
        'mandatory_rate'=>$mandatoryRate,
        'mandatory_days'=>$mandatoryDays,
        'usage_rate'=>$usageRate,
        'shortage'=>$shortage,
        'achieved'=>$achieved,
    ];
}
$employeeCount=count($mandatoryRows);
$averageUsageRate=$employeeCount?round($totalUsageRate/$employeeCount,1):0;
$achievementRate=$employeeCount?round($achievedCount/$employeeCount*100,1):0;
?>
<div class="page-title-row">
  <div>
    <h1>대시보드</h1>
    <p class="page-desc">권한 범위 내 직원·신청·결재·의무사용 현황을 조회합니다.</p>
  </div>
</div>

<form class="dashboard-search dashboard-search-extended" method="get">
  <select name="year" aria-label="조회연도">
    <?php for($y=(int)date('Y')+1;$y>=(int)date('Y')-5;$y--):?>
      <option value="<?=$y?>" <?=$year===$y?'selected':''?>><?=$y?>년</option>
    <?php endfor?>
  </select>
  <input name="q" value="<?=h($q)?>" placeholder="직원명, 사번, 부서, 휴가유형, 날짜 검색">
  <button class="btn">조회</button>
  <?php if($q || $year!==(int)date('Y')):?><a class="btn gray" href="dashboard.php">초기화</a><?php endif?>
</form>

<div class="grid">
  <?php foreach($stats as $k=>$v):?>
    <div class="col3 card"><div class="muted"><?=h($k)?></div><div class="stats"><?=h($v)?></div></div>
  <?php endforeach?>
</div>

<div class="card mandatory-admin-card">
  <div class="section-head">
    <div>
      <h2><?=$year?>년 연차 의무사용 현황</h2>
      <p class="muted">승인 완료된 연차 사용일수를 기준으로 계산합니다. 직원별 설정값이 없으면 관리자 기본 의무사용률을 적용합니다.</p>
    </div>
    <div class="mandatory-admin-summary">
      <span>대상 <strong><?=$employeeCount?>명</strong></span>
      <span>달성 <strong><?=$achievedCount?>명</strong></span>
      <span>달성률 <strong><?=$achievementRate?>%</strong></span>
      <span>평균 사용률 <strong><?=$averageUsageRate?>%</strong></span>
    </div>
  </div>

  <div class="table-wrap">
    <table class="table mandatory-admin-table">
      <thead><tr>
        <th>직원</th><th>부서</th><th>부여연차</th><th>승인사용</th><th>결재중</th><th>잔여연차</th>
        <th>현재 사용률</th><th>의무사용 목표</th><th>목표까지</th><th>상태</th>
      </tr></thead>
      <tbody>
      <?php if(!$mandatoryRows):?><tr><td colspan="10" class="empty-cell">조회 가능한 직원이 없습니다.</td></tr><?php endif?>
      <?php foreach($mandatoryRows as $m):$e=$m['employee'];?>
        <tr>
          <td><a class="text-link" href="leave_status.php?employee_id=<?=$e['id']?>&year=<?=$year?>"><?=h(trim($e['name'].' '.$e['position']))?></a><div class="muted"><?=h($e['employee_no']?:'-')?></div></td>
          <td><?=h($e['department_name']?:'-')?></td>
          <td><?=fmt_days($m['granted'])?>일</td>
          <td><strong><?=fmt_days($m['approved'])?>일</strong></td>
          <td><?=fmt_days($m['pending'])?>일</td>
          <td><?=fmt_days($m['remaining'])?>일</td>
          <td class="usage-cell">
            <div class="usage-value"><?=$m['usage_rate']?>%</div>
            <div class="mini-progress"><span style="width:<?=min(100,max(0,$m['usage_rate']))?>%"></span></div>
          </td>
          <td><?=fmt_days($m['mandatory_days'])?>일 <div class="muted"><?=fmt_days($m['mandatory_rate'])?>%</div></td>
          <td><?=$m['achieved']?'0일':fmt_days($m['shortage']).'일'?></td>
          <td><span class="status-pill <?=$m['achieved']?'success':'warning'?>"><?=$m['achieved']?'달성':'진행 중'?></span></td>
        </tr>
      <?php endforeach?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-head"><div><h2><?=$q?'검색 결과':'최근 신청'?></h2><p class="muted">직원명을 누르면 직원별 휴가현황으로 이동합니다.</p></div></div>
  <div class="table-wrap"><table class="table"><tr><th>직원</th><th>부서</th><th>유형</th><th>기간</th><th>일수</th><th>상태</th></tr>
    <?php if(!$recent):?><tr><td colspan="6" class="empty-cell">조회 결과가 없습니다.</td></tr><?php endif?>
    <?php foreach($recent as $r):?><tr><td><a class="text-link" href="leave_status.php?employee_id=<?=$r['employee_id']?>"><?=h($r['name'].' '.$r['position'])?></a><div class="muted"><?=h($r['employee_no'])?></div></td><td><?=h($r['department_name']?:'-')?></td><td><?=h($r['leave_name'])?></td><td><?=h($r['start_date'])?> ~ <?=h($r['end_date'])?></td><td><?=fmt_days($r['requested_days'])?></td><td><span class="badge"><?=h($r['status'])?></span></td></tr><?php endforeach?>
  </table></div>
</div>
<?php include '_bottom.php';?>
