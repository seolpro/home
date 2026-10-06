<?php
require_once __DIR__.'/lib.php';
try{ensure_v2_tables();echo '<meta charset="utf-8"><h2>stock_brief v2 설치 완료</h2><p>stock_briefs / stock_alimtalk_logs 테이블 준비가 완료되었습니다.</p><p>확인 후 이 파일은 삭제하세요.</p>';}catch(Throwable $e){http_response_code(500);echo '<pre>'.e($e->getMessage()).'</pre>';}
