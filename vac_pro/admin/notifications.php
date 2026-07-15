<?php
require_once __DIR__.'/../lib/app.php';require_once __DIR__.'/../core/Notification.php';$pageTitle='알림톡 관리';include '_top.php';$pdo=db();$msg='';$err='';
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();$action=$_POST['action']??'';
  if($action==='save'){$id=(int)($_POST['id']??0);$data=[trim($_POST['event_code']),trim($_POST['event_name']),trim($_POST['template_code']),trim($_POST['message_template']),trim($_POST['button_json']),trim($_POST['variable_help']),isset($_POST['is_active'])?1:0,(int)$_POST['sort_order']];if(!$data[0]||!$data[1])throw new RuntimeException('이벤트 코드와 업무명을 입력하세요.');if($id){$s=$pdo->prepare('UPDATE notification_templates SET event_code=?,event_name=?,template_code=?,message_template=?,button_json=?,variable_help=?,is_active=?,sort_order=? WHERE id=?');$s->execute([...$data,$id]);}else{$s=$pdo->prepare('INSERT INTO notification_templates(event_code,event_name,template_code,message_template,button_json,variable_help,is_active,sort_order) VALUES(?,?,?,?,?,?,?,?)');$s->execute($data);}$msg='템플릿을 저장했습니다.';}
  if($action==='retry'){Notification::retry((int)$_POST['queue_id']);$msg='재발송 대기열에 등록했습니다.';}
  if($action==='process'){$r=Notification::processPending(30);$msg="대기 {$r['total']}건 중 성공 {$r['sent']}건, 실패 {$r['failed']}건을 처리했습니다.";}
 }
}catch(Throwable $e){$err=$e->getMessage();}
$edit=null;if(isset($_GET['edit'])){$s=$pdo->prepare('SELECT * FROM notification_templates WHERE id=?');$s->execute([(int)$_GET['edit']]);$edit=$s->fetch();}
$templates=$pdo->query('SELECT * FROM notification_templates ORDER BY sort_order,id')->fetchAll();
$queue=$pdo->query("SELECT * FROM notification_queue ORDER BY id DESC LIMIT 100")->fetchAll();
?>
<div class="page-title-row notify-page-title">
  <div>
    <h1>알림톡 관리</h1>
    <p class="page-desc">업무별 승인 템플릿과 발송 대기열을 관리합니다.</p>
  </div>
</div>
<?php if($msg):?><div class="alert ok"><?=h($msg)?></div><?php endif?>
<?php if($err):?><div class="alert err"><?=h($err)?></div><?php endif?>

<div class="notify-layout">
  <section class="card notify-editor">
    <div class="panel-title">
      <div>
        <h2><?=$edit?'템플릿 수정':'템플릿 등록'?></h2>
        <p>승인된 알림톡 정보와 문구를 그대로 등록하세요.</p>
      </div>
      <?php if($edit):?><a class="btn sm ghost" href="notifications.php">신규 등록</a><?php endif?>
    </div>

    <form method="post" class="erp-form notify-form">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?=h($edit['id']??'')?>">

      <div class="erp-section">
        <div class="erp-section-head">
          <span class="erp-step">01</span>
          <div><h3>기본 정보</h3><p>시스템에서 사용할 이벤트와 승인 템플릿 코드를 입력합니다.</p></div>
        </div>
        <div class="form-grid cols-2 notify-basic-grid">
          <label>이벤트 코드 <span class="required">필수</span>
            <input name="event_code" value="<?=h($edit['event_code']??'')?>" placeholder="예: leave.submit" required>
            <small>소스에서 호출할 고유 코드입니다.</small>
          </label>
          <label>업무명 <span class="required">필수</span>
            <input name="event_name" value="<?=h($edit['event_name']??'')?>" placeholder="예: 휴가 신청 결재 요청" required>
          </label>
          <label class="notify-wide-field">비즈뿌리오 템플릿 코드
            <input name="template_code" value="<?=h($edit['template_code']??'')?>" placeholder="비즈뿌리오에서 승인된 템플릿 코드">
          </label>
          <label>표시순서
            <input type="number" name="sort_order" value="<?=h($edit['sort_order']??0)?>" min="0">
          </label>
        </div>
      </div>

      <div class="erp-section">
        <div class="erp-section-head">
          <span class="erp-step">02</span>
          <div><h3>메시지 및 버튼</h3><p>승인된 문구와 띄어쓰기까지 동일하게 입력하세요.</p></div>
        </div>
        <label>승인된 템플릿 메시지 본문
          <textarea class="notify-message" name="message_template" rows="10" placeholder="예: #{직원명}님의 휴가 신청이 접수되었습니다."><?=h($edit['message_template']??'')?></textarea>
        </label>
        <label>버튼 JSON
          <textarea class="notify-json" name="button_json" rows="6" placeholder='[{"name":"확인","type":"WL","url_mobile":"#{url}","url_pc":"#{url}"}]'><?=h($edit['button_json']??'')?></textarea>
          <small>버튼이 없으면 비워두세요.</small>
        </label>
      </div>

      <div class="erp-section">
        <div class="erp-section-head">
          <span class="erp-step">03</span>
          <div><h3>변수 및 사용상태</h3><p>템플릿 작성 시 사용할 치환 변수를 기록합니다.</p></div>
        </div>
        <input type="hidden" name="variable_help" id="variable_help" value="<?=h($edit['variable_help']??'')?>">
        <div class="notify-variable-box">
          <div class="notify-variable-head">
            <div>
              <strong>사용 가능한 변수</strong>
              <small>아래 변수를 누르면 메시지 본문의 현재 커서 위치에 자동으로 삽입됩니다.</small>
            </div>
            <button type="button" class="btn sm ghost" id="copyVariablesBtn">변수목록 복사</button>
          </div>
          <div id="variableChips" class="notify-variable-chips" aria-live="polite"></div>
          <div class="notify-variable-note">
            비즈뿌리오에서 승인된 템플릿의 변수명과 <strong>철자·띄어쓰기·대소문자까지 동일</strong>해야 합니다.
          </div>
        </div>
        <label class="toggle-row notify-active-toggle">
          <input type="checkbox" name="is_active" value="1" <?=!$edit||$edit['is_active']?'checked':''?>>
          <span><strong>이 템플릿 사용</strong><small>해제하면 해당 업무의 알림톡 발송이 중지됩니다.</small></span>
        </label>
      </div>

      <div class="form-actions">
        <button class="btn">템플릿 저장</button>
        <?php if($edit):?><a class="btn gray" href="notifications.php">취소</a><?php endif?>
      </div>
    </form>
  </section>

  <section class="card notify-template-list">
    <div class="section-head">
      <div><h2>업무별 템플릿</h2><p class="muted">등록된 업무별 템플릿과 사용상태입니다.</p></div>
      <form method="post" class="notify-process-form">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <button class="btn sm" name="action" value="process">대기열 지금 처리</button>
      </form>
    </div>
    <div class="table-scroll">
      <table class="table notify-template-table">
        <tr><th>업무</th><th>이벤트 코드</th><th>템플릿 코드</th><th>상태</th><th></th></tr>
        <?php foreach($templates as $t):?><tr>
          <td><strong><?=h($t['event_name'])?></strong></td>
          <td><code><?=h($t['event_code'])?></code></td>
          <td><?=h($t['template_code']?:'미입력')?></td>
          <td><span class="badge <?=$t['is_active']?'ok-badge':'off-badge'?>"><?=$t['is_active']?'사용':'중지'?></span></td>
          <td><a class="btn sm ghost" href="?edit=<?=$t['id']?>">수정</a></td>
        </tr><?php endforeach?>
      </table>
    </div>
  </section>
</div>

<section class="card notify-queue-card">
  <div class="section-head"><div><h2>최근 발송 대기열</h2><p class="muted">최근 등록된 발송 건과 처리 결과입니다.</p></div></div>
  <div class="table-scroll"><table class="table"><tr><th>등록</th><th>업무</th><th>수신번호</th><th>상태</th><th>시도</th><th>오류</th><th></th></tr><?php foreach($queue as $q):?><tr><td><?=h($q['created_at'])?></td><td><?=h($q['event_code'])?></td><td><?=h($q['phone'])?></td><td><span class="badge"><?=h($q['status'])?></span></td><td><?=$q['attempts']?></td><td class="small-text"><?=h($q['last_error'])?></td><td><?php if(in_array($q['status'],['failed','retry'],true)):?><form method="post"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="queue_id" value="<?=$q['id']?>"><button class="btn sm" name="action" value="retry">재발송</button></form><?php endif?></td></tr><?php endforeach?></table></div>
</section>

<script>
(function(){
  const eventInput = document.querySelector('input[name="event_code"]');
  const messageBox = document.querySelector('textarea[name="message_template"]');
  const hiddenHelp = document.getElementById('variable_help');
  const chipBox = document.getElementById('variableChips');
  const copyBtn = document.getElementById('copyVariablesBtn');

  const common = [
    ['#{직원명}','신청 직원 성명'], ['#{직책}','직책'], ['#{부서}','부서'],
    ['#{휴가종류}','휴가 유형'], ['#{기간}','사용 기간'], ['#{사용일수}','사용 일수'],
    ['#{신청사유}','신청 사유'], ['#{결재자}','현재 결재자'], ['#{반려사유}','반려 의견'],
    ['#{신청일}','신청 일자'], ['#{처리일}','승인·반려 처리일'], ['#{url}','확인 페이지 주소']
  ];
  const byEvent = {
    'request': ['#{직원명}','#{직책}','#{부서}','#{휴가종류}','#{기간}','#{사용일수}','#{신청사유}','#{결재자}','#{url}'],
    'leave.submit': ['#{직원명}','#{직책}','#{부서}','#{휴가종류}','#{기간}','#{사용일수}','#{신청사유}','#{결재자}','#{url}'],
    'next_approval': ['#{직원명}','#{휴가종류}','#{기간}','#{사용일수}','#{결재자}','#{url}'],
    'approved': ['#{직원명}','#{휴가종류}','#{기간}','#{사용일수}','#{처리일}','#{url}'],
    'rejected': ['#{직원명}','#{휴가종류}','#{기간}','#{반려사유}','#{결재자}','#{처리일}','#{url}']
  };
  const descriptions = Object.fromEntries(common);

  function currentVariables(){
    const code = (eventInput?.value || '').trim();
    if (byEvent[code]) return byEvent[code];
    const saved = (hiddenHelp?.value || '').match(/#\{[^}]+\}/g);
    return saved && saved.length ? [...new Set(saved)] : common.map(v=>v[0]);
  }
  function syncHidden(vars){
    if(hiddenHelp) hiddenHelp.value = vars.join(', ');
  }
  function insertAtCursor(textarea, text){
    if(!textarea) return;
    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? textarea.value.length;
    const before = textarea.value.slice(0,start);
    const after = textarea.value.slice(end);
    textarea.value = before + text + after;
    const pos = start + text.length;
    textarea.focus();
    textarea.setSelectionRange(pos,pos);
    textarea.dispatchEvent(new Event('input',{bubbles:true}));
  }
  function render(){
    const vars = currentVariables();
    syncHidden(vars);
    chipBox.innerHTML='';
    vars.forEach(v=>{
      const b=document.createElement('button');
      b.type='button'; b.className='notify-variable-chip'; b.textContent=v;
      b.title=descriptions[v] ? descriptions[v]+' — 클릭하여 본문에 삽입' : '클릭하여 본문에 삽입';
      b.addEventListener('click',()=>insertAtCursor(messageBox,v));
      chipBox.appendChild(b);
    });
  }
  eventInput?.addEventListener('input',render);
  copyBtn?.addEventListener('click', async()=>{
    const text=currentVariables().join(', ');
    try{ await navigator.clipboard.writeText(text); copyBtn.textContent='복사 완료'; }
    catch(e){ window.prompt('아래 변수목록을 복사하세요.',text); }
    setTimeout(()=>copyBtn.textContent='변수목록 복사',1200);
  });
  render();
})();
</script>

<?php include '_bottom.php';?>
