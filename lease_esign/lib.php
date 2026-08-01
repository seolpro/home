<?php
declare(strict_types=1);
function cfg(?string $key=null){static $c;if(!$c){$f=__DIR__.'/config.php';if(!is_file($f))die('설치가 필요합니다. install.php를 실행하세요.');$c=require $f;date_default_timezone_set($c['timezone']??'Asia/Seoul');}if($key===null)return $c;$v=$c;foreach(explode('.',$key) as $p)$v=$v[$p]??null;return $v;}
function db():PDO{static $p;if($p)return $p;$d=cfg('db');$p=new PDO("mysql:host={$d['host']};port=".($d['port']??3306).";dbname={$d['name']};charset=".($d['charset']??'utf8mb4'),$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);return $p;}
function e($s):string{return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function json_out($a,int $code=200):never{http_response_code($code);header('Content-Type: application/json; charset=utf-8');echo json_encode($a,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function token(int $bytes=24):string{return bin2hex(random_bytes($bytes));}
function hash_token(string $t):string{return hash_hmac('sha256',$t,(string)cfg('security.app_key'));}
function csrf():string{if(session_status()!==PHP_SESSION_ACTIVE)session_start();return $_SESSION['csrf']??=token(16);}
function verify_csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??''))throw new RuntimeException('요청 확인값이 올바르지 않습니다.');}
function admin_required():void{if(session_status()!==PHP_SESSION_ACTIVE)session_start();if(empty($_SESSION['admin'])){header('Location: login.php');exit;}}
function money($n):string{return number_format((int)$n).'원';}
function client_ip():string{return substr($_SERVER['REMOTE_ADDR']??'',0,45);}
function audit(int $cid,string $actor,string $action,array $meta=[]):void{$s=db()->prepare('INSERT INTO audit_logs(contract_id,actor_type,action,ip,user_agent,meta_json,created_at) VALUES(?,?,?,?,?,?,NOW())');$s->execute([$cid,$actor,$action,client_ip(),substr($_SERVER['HTTP_USER_AGENT']??'',0,500),json_encode($meta,JSON_UNESCAPED_UNICODE)]);}
function contract_by_token(string $t,string $party):array{$col=$party==='lessor'?'lessor_token_hash':'lessee_token_hash';$s=db()->prepare("SELECT * FROM contracts WHERE $col=? AND token_expires_at>=NOW() LIMIT 1");$s->execute([hash_token($t)]);$r=$s->fetch();if(!$r)throw new RuntimeException('유효하지 않거나 만료된 계약 링크입니다.');return $r;}
function default_terms(): string
{
    return <<<'TXT'
제1조 (목적)
임대인과 임차인은 본 계약서에 표시된 부동산에 관하여 상호 신의성실의 원칙에 따라 주거용 임대차계약을 체결하며, 임대차의 목적물, 보증금, 월차임, 임대차기간 및 기타 계약조건은 본 계약서에 기재된 내용에 따른다.

제2조 (보증금 및 월차임)
① 임차인은 임대인에게 본 계약서에 기재된 보증금을 약정한 방법과 기한에 따라 지급한다.

② 임차인은 본 계약서에 기재된 월차임을 부동산 명도일을 기준으로 매월 해당일에 임대인이 지정한 계좌로 지급한다.

③ 월차임 지급일이 금융기관의 휴무일인 경우에는 그다음 영업일까지 지급할 수 있다.

④ 임차인이 월차임 지급을 지체한 경우에는 미지급 금액에 대하여 연 10%의 비율로 계산한 지연손해금을 지급한다. 지연손해금은 지급기일 다음 날부터 실제 지급일까지 일할 계산한다.

⑤ 계좌이체 수수료 등 월차임 지급에 필요한 비용은 별도의 약정이 없는 한 임차인이 부담한다.

제3조 (목적물의 인도)
① 임대인은 본 계약서에 기재된 명도일에 임차인이 목적물을 정상적으로 사용·수익할 수 있는 상태로 인도한다.

② 임차인은 목적물을 인도받을 때 시설물의 상태와 하자 여부를 확인하여야 하며, 발견된 하자가 있는 경우 지체 없이 임대인에게 알려야 한다.

③ 임대인은 임차인이 목적물을 계약 목적에 맞게 사용할 수 있도록 필요한 상태를 유지하여야 한다. 다만, 임차인의 고의 또는 과실로 발생한 손상이나 고장은 임차인이 부담한다.

제4조 (임대차기간)
① 임대차기간은 본 계약서에 기재된 시작일부터 종료일까지로 한다.

② 계약기간 만료 후 계약갱신, 묵시적 갱신 및 계약갱신요구권에 관한 사항은 「주택임대차보호법」 등 관계 법령에 따른다.

③ 계약 당사자가 계약기간 종료 전에 갱신 또는 종료의 의사를 표시하려는 경우에는 관계 법령에서 정한 기간과 방법을 준수하여야 한다.

제5조 (목적물의 사용 및 관리)
① 임차인은 목적물을 주거용으로 사용하며, 선량한 관리자의 주의로 목적물과 부속 시설물을 사용·관리하여야 한다.

② 임차인은 임대인의 사전 서면동의 없이 목적물의 구조, 용도 또는 주요 시설을 변경하거나 개축·증축·철거 또는 변조할 수 없다.

③ 임차인이 임대인의 동의를 받아 시설을 설치하거나 변경한 경우, 계약 종료 시 임차인의 비용으로 이를 철거하고 원상회복하여야 한다. 다만, 임대인이 현 상태로 인수하기로 서면 동의한 경우에는 그러하지 아니하다.

④ 통상적인 사용에 따라 발생한 마모·노후·변색 및 임차인의 책임 없는 시설물의 고장은 임차인의 원상회복 의무에서 제외한다.

⑤ 임차인은 화재, 누수, 파손 등 목적물에 중대한 손해가 발생하거나 발생할 우려가 있는 사실을 알게 된 경우 즉시 임대인에게 알려야 한다.

제6조 (수선 및 비용부담)
① 목적물의 구조, 주요 설비 및 기본적인 주거 기능을 유지하기 위하여 필요한 수선은 임대인이 부담한다.

② 전구, 소모품 교체 등 통상적인 사용으로 발생하는 경미한 관리비용과 임차인의 고의 또는 과실로 발생한 수선비용은 임차인이 부담한다.

③ 긴급한 수선이 필요한 경우 임차인은 임대인에게 우선 통지하여야 한다. 다만, 임대인에게 즉시 연락하기 어렵고 손해의 확대를 막기 위하여 불가피한 경우에는 필요한 범위에서 조치한 뒤 지체 없이 임대인에게 알려야 한다.

제7조 (금지행위 및 전대)
① 임차인은 임대인의 사전 서면동의 없이 목적물의 전부 또는 일부를 제3자에게 전대하거나 임차권을 양도할 수 없다.

② 임차인은 목적물에서 관계 법령에 위반되는 행위, 위험물 보관, 과도한 소음·진동 또는 이웃의 주거생활을 현저히 방해하는 행위를 하여서는 아니 된다.

③ 임차인은 임대인의 동의 없이 목적물의 용도를 변경하거나 사업장, 숙박업소 또는 불특정 다수가 이용하는 장소로 사용할 수 없다.

제8조 (계약의 해지)
① 임차인이 2기의 차임액에 해당하는 금액을 연체한 경우 임대인은 관계 법령에 따라 계약을 해지할 수 있다.

② 임차인이 본 계약의 중요한 의무를 위반한 경우 임대인은 상당한 기간을 정하여 그 시정을 요구하고, 해당 기간 내에 시정되지 않을 때에는 관계 법령에 따라 계약을 해지할 수 있다. 다만, 위반행위의 성질상 시정이 불가능하거나 계약관계를 계속하기 어려운 중대한 사유가 있는 경우에는 관계 법령에 따른다.

③ 임대인의 귀책사유로 목적물을 정상적으로 사용·수익할 수 없고 상당한 기간 내에 그 상태가 시정되지 않은 경우, 임차인은 관계 법령에 따라 계약을 해지할 수 있다.

④ 계약 해지와 손해배상에 관한 사항은 「민법」, 「주택임대차보호법」 및 기타 관계 법령에 따른다.

제9조 (계약 종료와 목적물 반환)
① 임대차계약이 종료된 경우 임차인은 목적물과 임대인으로부터 제공받은 열쇠, 출입카드 및 부속품을 임대인에게 반환하여야 한다.

② 임차인은 계약 종료일까지 월차임, 관리비, 공과금 및 기타 부담금을 정산하여야 한다.

③ 임차인의 고의 또는 과실로 목적물이 훼손된 경우 임차인은 이를 원상회복하거나 그에 상당하는 손해를 배상하여야 한다.

④ 임차인이 목적물을 반환하지 않는 경우 발생하는 사용료 및 손해배상에 관한 사항은 관계 법령에 따른다.

제10조 (보증금의 반환)
① 임대인은 임대차계약이 종료되고 임차인이 목적물을 반환함과 동시에 보증금을 임차인에게 반환한다.

② 임차인에게 미지급 월차임, 관리비, 공과금, 원상회복비 또는 손해배상금 등 정당한 채무가 있는 경우 임대인은 그 금액을 보증금에서 공제한 후 나머지 금액을 반환할 수 있다.

③ 임대인이 보증금에서 금액을 공제하는 경우에는 그 공제 사유와 금액을 임차인에게 알려야 한다.

④ 보증금 반환과 목적물 반환은 특별한 사정이 없는 한 동시에 이행한다.

제11조 (관리비 및 공과금)
① 관리비, 전기료, 수도료, 가스료, 통신비 및 기타 임차인의 사용으로 발생하는 비용은 임차인이 부담한다.

② 소유권에 기초하여 부과되는 세금 및 특별한 약정이 없는 장기수선 관련 비용은 임대인이 부담한다.

③ 관리비의 부담 항목과 납부 방법에 별도의 약정이 있는 경우에는 그 약정에 따른다.

제12조 (특약사항)
① 임차인의 고의 또는 과실로 목적물이나 부속 시설물이 훼손된 경우 임차인은 이를 원상회복하거나 그 손해를 배상한다.

② 임차인은 임대인의 사전 서면동의 없이 목적물의 전부 또는 일부를 제3자에게 전대하거나 임차권을 양도할 수 없다.

③ 본 계약서에 별도로 작성된 특약사항은 본 계약의 일부를 구성한다.

④ 특약사항이 「민법」, 「주택임대차보호법」 등 강행법규에 위반되거나 임차인에게 법률상 인정되는 권리를 부당하게 제한하는 경우에는 해당 부분에 한하여 효력이 제한될 수 있다.

제13조 (전자문서 및 전자서명)
① 임대인과 임차인은 본 계약을 전자문서로 작성하고 전자적 방식으로 확인·서명하는 것에 동의한다.

② 본 계약의 전자서명은 당사자가 본인 확인 절차를 거쳐 계약 내용을 확인한 후 직접 입력한 서명으로 한다.

③ 전자서명 일시, 접속기록, 전자서명 이미지, 문서 확인값 및 계약 처리 이력은 계약 체결 사실과 문서의 무결성을 확인하기 위한 자료로 보관할 수 있다.

④ 임대인과 임차인의 서명이 모두 완료된 최종 전자문서와 그 문서를 변환하여 생성한 PDF 문서는 계약 당사자가 합의한 계약서로 본다.

⑤ 전자문서 또는 전자서명이라는 이유만으로 해당 계약의 효력이 부인되지 아니하며, 그 효력과 증명력은 관계 법령에 따른다.

제14조 (개인정보의 처리)
① 계약 당사자는 계약 체결, 본인 확인, 계약 이행, 전자서명 기록 및 분쟁 대응에 필요한 범위에서 상대방의 개인정보가 처리될 수 있음에 동의한다.

② 수집된 개인정보와 전자서명 기록은 관련 법령 및 정해진 보유기간에 따라 안전하게 관리하며, 계약 목적 외의 용도로 사용하지 않는다.

③ 주민등록번호 등 고유식별정보는 관계 법령상 근거가 있거나 당사자의 별도 동의를 받은 경우에 한하여 필요한 최소 범위에서 처리한다.

제15조 (통지)
① 본 계약과 관련한 통지는 계약서에 기재된 주소, 휴대전화번호 또는 전자적 연락수단으로 할 수 있다.

② 계약 당사자는 주소나 연락처가 변경된 경우 지체 없이 상대방에게 알려야 한다.

③ 변경 사실을 알리지 않아 기존 연락처로 발송된 통지를 확인하지 못한 경우 그 책임은 변경 사실을 알리지 않은 당사자에게 있다. 다만, 관계 법령에서 달리 정한 경우에는 그에 따른다.

제16조 (분쟁 해결 및 준거법)
① 계약 당사자는 본 계약과 관련한 분쟁이 발생한 경우 상호 협의하여 원만하게 해결하도록 노력한다.

② 협의로 해결되지 않는 경우에는 「민법」, 「주택임대차보호법」, 「전자문서 및 전자거래 기본법」, 「전자서명법」 및 기타 대한민국 관계 법령에 따른다.

③ 관할법원은 관계 법령에서 정한 법원으로 한다.
TXT;
}
function render_contract(array $c,bool $pdf=false):string{ob_start();include __DIR__.'/contract_template.php';return ob_get_clean();}
function ppurio_token():array{
    $s=cfg('sms');
    if(empty($s['account'])||empty($s['auth_key']))return ['ok'=>false,'message'=>'뿌리오 계정 또는 인증키가 없습니다.'];
    if(!function_exists('curl_init'))return ['ok'=>false,'message'=>'PHP cURL 확장이 필요합니다.'];
    $ch=curl_init('https://message.ppurio.com/v1/token');
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20,
        CURLOPT_HTTPHEADER=>['Authorization: Basic '.base64_encode($s['account'].':'.$s['auth_key']),'Content-Type: application/json; charset=utf-8']
    ]);
    $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    $json=json_decode((string)$body,true);
    $token=is_array($json)?($json['token']??null):null;
    return ['ok'=>(bool)$token,'token'=>$token,'code'=>$code,'body'=>$body,'error'=>$err,'message'=>$token?'토큰 발급 성공':'뿌리오 토큰 발급 실패'];
}
function sms_type(string $message):string{
    $encoded=mb_convert_encoding($message,'EUC-KR','UTF-8');
    return strlen($encoded)<=90?'SMS':'LMS';
}
function send_sms(string $phone,string $message,string $name='수신자'):array{
    $s=cfg('sms');
    if(empty($s['enabled']))return ['ok'=>false,'message'=>'문자 설정이 비활성화되어 있습니다.','dry_run'=>true];
    if(($s['provider']??'ppurio')!=='ppurio')return ['ok'=>false,'message'=>'지원하지 않는 문자 공급자입니다.'];
    $to=preg_replace('/\D/','',$phone);$from=preg_replace('/\D/','',(string)($s['sender']??''));
    if(!preg_match('/^01\d{8,9}$/',$to))return ['ok'=>false,'message'=>'수신번호 형식이 올바르지 않습니다.'];
    if(!$from)return ['ok'=>false,'message'=>'발신번호가 설정되지 않았습니다.'];
    $tk=ppurio_token();if(empty($tk['ok']))return $tk;
    $payload=[
        'account'=>$s['account'],'messageType'=>sms_type($message),'content'=>$message,'from'=>$from,
        'duplicateFlag'=>'Y','targetCount'=>1,'refKey'=>'lease_'.date('YmdHis').'_'.token(4),
        'targets'=>[['to'=>$to,'name'=>$name,'changeWord'=>['var1'=>$name]]]
    ];
    $ch=curl_init('https://message.ppurio.com/v1/message');
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tk['token'],'Content-Type: application/json; charset=utf-8']
    ]);
    $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    $json=json_decode((string)$body,true);$apiCode=is_array($json)?(string)($json['code']??''):'';
    $ok=$code>=200&&$code<300&&($apiCode===''||in_array($apiCode,['1000','200'],true));
    return ['ok'=>$ok,'http_code'=>$code,'api_code'=>$apiCode,'response'=>$json?:$body,'error'=>$err,'message'=>$ok?'문자 발송 요청 성공':'문자 발송 실패'];
}
function sms_log(int $contractId,string $phone,string $message,array $result):void{
    $q=db()->prepare('INSERT INTO sms_logs(contract_id,recipient,message,result_json,created_at) VALUES(?,?,?,?,NOW())');
    $q->execute([$contractId,$phone,$message,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
}
function notify_lessee_signed(array $c):array{
    $s=cfg('sms');$recipients=[];
    if(!empty($c['lessor_phone']))$recipients[]=['phone'=>$c['lessor_phone'],'name'=>$c['lessor_name']?:'임대인','role'=>'lessor'];
    if(!empty($s['admin_phone'])&&preg_replace('/\D/','',$s['admin_phone'])!==preg_replace('/\D/','',(string)$c['lessor_phone']))$recipients[]=['phone'=>$s['admin_phone'],'name'=>'관리자','role'=>'admin'];
    $msg="[임대차 전자계약 서명완료]\n{$c['lessee_name']} 임차인이 계약서 서명을 완료했습니다.\n관리자 화면에서 최종 PDF를 생성한 후 임차인에게 발송해 주세요.\n계약번호: {$c['id']}";
    $all=[];$any=false;
    foreach($recipients as $r){$res=send_sms($r['phone'],$msg,$r['name']);sms_log((int)$c['id'],$r['phone'],$msg,$res);$all[]=['role'=>$r['role'],'result'=>$res];if(!empty($res['ok']))$any=true;}
    return ['ok'=>$any,'results'=>$all,'message'=>$recipients?'서명 완료 알림 처리':'알림 수신번호 없음'];
}
