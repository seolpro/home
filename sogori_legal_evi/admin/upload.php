<?php
require dirname(__DIR__).'/config.php'; admin_required(); check_csrf(); ensure_dirs();
$spot=(int)($_POST['spot']??0); if($spot<1||$spot>5) exit('SPOT 오류');
$files=$_FILES['photos']??null; if(!$files) exit('파일 없음');
$d=load_data(); $ok=0; $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
for($i=0;$i<count($files['name']);$i++){
 if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) continue;
 if(($files['size'][$i]??0)>MAX_UPLOAD_MB*1024*1024) continue;
 $tmp=$files['tmp_name'][$i]; $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp); if(!isset($allowed[$mime])) continue;
 $id=date('YmdHis').'-'.bin2hex(random_bytes(5)); $name=$id.'.'.$allowed[$mime]; $dest=UPLOAD_DIR.'/'.$name;
 if(!move_uploaded_file($tmp,$dest)) continue;
 $d['items'][]=['id'=>$id,'spot'=>$spot,'file'=>'uploads/'.$name,'original_name'=>(string)$files['name'][$i],'sha256'=>hash_file('sha256',$dest),'uploaded_at'=>date('Y-m-d H:i:s'),'taken_at'=>str_replace('T',' ',(string)($_POST['taken_at']??'')),'title'=>trim((string)($_POST['title']??'')),'direction'=>trim((string)($_POST['direction']??'')),'observation'=>trim((string)($_POST['observation']??'')),'purpose'=>trim((string)($_POST['purpose']??''))]; $ok++;
}
save_data($d); header('Location:index.php?m='.urlencode($ok.'개 사진을 등록했습니다.')); exit;
