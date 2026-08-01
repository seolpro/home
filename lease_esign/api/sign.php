<?php
require_once dirname(__DIR__).'/lib.php';
try{
    $in=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    $party=($in['party']??'')==='lessor'?'lessor':'lessee';
    $c=contract_by_token((string)($in['token']??''),$party);
    $phone=$party==='lessor'?$c['lessor_phone']:$c['lessee_phone'];
    if(substr(preg_replace('/\D/','',$phone),-4)!==($in['phone4']??''))throw new RuntimeException('휴대전화 번호 뒤 4자리가 일치하지 않습니다.');
    if(empty($in['agree']))throw new RuntimeException('전자계약 동의가 필요합니다.');
    if($party==='lessee'&&!$c['lessor_signed_at'])throw new RuntimeException('임대인 서명이 먼저 필요합니다.');
    $data=(string)($in['signature']??'');
    if(!preg_match('#^data:image/png;base64,#',$data))throw new RuntimeException('서명 이미지가 올바르지 않습니다.');
    $bin=base64_decode(substr($data,strpos($data,',')+1),true);
    if($bin===false||strlen($bin)>1500000)throw new RuntimeException('서명 이미지 크기가 올바르지 않습니다.');
    $dir=dirname(__DIR__).'/storage/signatures';if(!is_dir($dir))mkdir($dir,0755,true);
    $name=$c['id'].'_'.$party.'_'.date('YmdHis').'_'.token(4).'.png';
    if(file_put_contents($dir.'/'.$name,$bin)===false)throw new RuntimeException('서명 파일 저장에 실패했습니다.');
    $web='storage/signatures/'.$name;$sigCol=$party.'_signature';$atCol=$party.'_signed_at';$ipCol=$party.'_ip';
    $newStatus=$party==='lessor'?'lessor_signed':'completed';
    $hash=hash('sha256',json_encode([$c['id'],$c['property_address'],$c['deposit'],$c['monthly_rent'],$c['start_date'],$c['end_date'],$c['terms'],$web],JSON_UNESCAPED_UNICODE));
    $sql="UPDATE contracts SET $sigCol=?,$atCol=NOW(),$ipCol=?,status=?,document_hash=?,updated_at=NOW(),finalized_at=".($party==='lessee'?'NOW()':'finalized_at')." WHERE id=? AND $atCol IS NULL";
    $s=db()->prepare($sql);$s->execute([$web,client_ip(),$newStatus,$hash,$c['id']]);
    if(!$s->rowCount())throw new RuntimeException('이미 서명되었거나 처리할 수 없습니다.');
    audit((int)$c['id'],$party,'signed',['document_hash'=>$hash]);
    $notice=null;
    if($party==='lessee'){
        $fresh=db()->prepare('SELECT * FROM contracts WHERE id=?');$fresh->execute([$c['id']]);$notice=notify_lessee_signed($fresh->fetch()?:$c);
        audit((int)$c['id'],'system','lessee_signed_sms_processed',['sms_ok'=>$notice['ok']??false]);
    }
    json_out(['ok'=>true,'message'=>$party==='lessor'?'임대인 서명이 완료되었습니다. 이제 임차인에게 전달할 수 있습니다.':'임차인 서명이 완료되었습니다. 임대인에게 완료 사실이 안내되었습니다. 최종 계약서는 PDF 생성 후 문자로 전달됩니다.','notice'=>$notice]);
}catch(Throwable $e){json_out(['ok'=>false,'message'=>$e->getMessage()],400);}
