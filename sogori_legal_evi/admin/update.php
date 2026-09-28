<?php
require dirname(__DIR__).'/config.php';
admin_required();
check_csrf();

$id=(string)($_POST['id']??'');
if($id===''){
    header('Location:index.php?m='.urlencode('수정할 사진 정보가 없습니다.'));
    exit;
}

$spot=(int)($_POST['spot']??0);
if($spot<1||$spot>5){
    header('Location:index.php?m='.urlencode('SPOT 값이 올바르지 않습니다.'));
    exit;
}

$d=load_data();
$found=false;

foreach($d['items'] as $k=>$it){
    if((string)($it['id']??'')!==$id) continue;

    /* 사진 파일 및 해시는 건드리지 않고 설명정보만 변경 */
    $d['items'][$k]['spot']=$spot;
    $d['items'][$k]['taken_at']=str_replace('T',' ',(string)($_POST['taken_at']??''));
    $d['items'][$k]['title']=trim((string)($_POST['title']??''));
    $d['items'][$k]['direction']=trim((string)($_POST['direction']??''));
    $d['items'][$k]['observation']=trim((string)($_POST['observation']??''));
    $d['items'][$k]['purpose']=trim((string)($_POST['purpose']??''));

    $found=true;
    break;
}

if(!$found){
    header('Location:index.php?m='.urlencode('해당 사진을 찾을 수 없습니다.'));
    exit;
}

save_data($d);
header('Location:index.php?m='.urlencode('사진 설명을 수정했습니다.'));
exit;
