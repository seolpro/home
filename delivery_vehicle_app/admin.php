<?php
require __DIR__ . '/common.php';
$error = ''; $notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    if ($action === 'login') {
        if (!empty($cfg['admin_password_hash']) && password_verify(post('password'), $cfg['admin_password_hash'])) {
            session_regenerate_id(true); $_SESSION['admin'] = true;
            header('Location: admin.php'); exit;
        }
        $error = '비밀번호가 일치하지 않습니다.';
    } elseif ($action === 'logout') {
        $_SESSION = []; session_destroy(); header('Location: admin.php'); exit;
    } elseif (logged()) {
        $id = (int)post('id');
        if ($action === 'add') {
            $label = post('label'); $name = post('name'); $p = phone(post('phone'));
            if (!$label || mb_strlen($label) > 80 || !$name || mb_strlen($name) > 80 || !valid_phone($p)) $error = '수신인 정보를 확인해 주세요.';
            else {
                $s = db()->prepare('INSERT INTO dv_recipients(label,recipient_name,recipient_phone,token) VALUES(?,?,?,?)');
                $s->execute([$label,$name,$p,bin2hex(random_bytes(24))]); $notice = '수신인을 등록했습니다.';
            }
        } elseif ($action === 'edit') {
            $label = post('label'); $name = post('name'); $p = phone(post('phone'));
            if (!$label || mb_strlen($label)>80 || !$name || mb_strlen($name)>80 || !valid_phone($p)) $error = '수정할 정보를 확인해 주세요.';
            else { $s=db()->prepare('UPDATE dv_recipients SET label=?,recipient_name=?,recipient_phone=? WHERE id=?'); $s->execute([$label,$name,$p,$id]); $notice='수신인 정보를 수정했습니다.'; }
        } elseif ($action === 'toggle') {
            $s=db()->prepare('UPDATE dv_recipients SET active=1-active WHERE id=?'); $s->execute([$id]); $notice='사용 상태를 변경했습니다.';
        } elseif ($action === 'rotate') {
            $s=db()->prepare('UPDATE dv_recipients SET token=?,short_url=NULL WHERE id=?'); $s->execute([bin2hex(random_bytes(24)),$id]); $notice='새 신청 링크를 발급했습니다. 기존 링크는 무효입니다.';
        } elseif ($action === 'shorten') {
            $s=db()->prepare('SELECT token,short_url FROM dv_recipients WHERE id=?'); $s->execute([$id]); $r=$s->fetch();
            if (!$r) $error='수신인을 찾을 수 없습니다.';
            elseif ($r['short_url']) $notice='이미 단축 URL이 생성되어 있습니다.';
            else {
                $result=dv_bitly_shorten(url('index.php?key='.$r['token']));
                if (empty($result['ok'])) $error=$result['message'] ?? '단축 URL 생성에 실패했습니다.';
                else { $s=db()->prepare('UPDATE dv_recipients SET short_url=? WHERE id=? AND token=?'); $s->execute([$result['link'],$id,$r['token']]); $notice='단축 URL을 생성했습니다.'; }
            }
        } elseif ($action === 'retry') {
            $s=db()->prepare('SELECT q.*,r.recipient_phone,r.active FROM dv_requests q JOIN dv_recipients r ON r.id=q.recipient_id WHERE q.id=?');
            $s->execute([$id]); $q=$s->fetch();
            if (!$q || !$q['active']) $error='재발송할 수 없습니다. 수신인 사용 상태를 확인하세요.';
            else {
                $result=ppurio_send($q['recipient_phone'],['market'=>$q['market'],'visit_date'=>$q['visit_date'],'vehicle_no'=>$q['vehicle_no'],'created_at'=>$q['created_at']]);
                dv_record_attempt($id,$result);
                $s=db()->prepare('UPDATE dv_requests SET retry_count=retry_count+1 WHERE id=?'); $s->execute([$id]);
                $notice='재발송 '.($result['ok']?'API 접수':'접수 실패').' (HTTP '.($result['http']??'-').'): '.($result['message']??'');
            }
        }
    }
}
layout_start('차량등록 관리');
?>
<style>
/* 관리자 화면에만 적용 — 기존 index.php 스타일 영향 없음 */
.wrap{max-width:1280px;margin:20px auto;padding:0 20px}.wrap>header{display:none}
.dv-admin{--navy:#172b4d;--blue:#2563eb;--line:#e6eaf1;--soft:#f7f9fc;color:#26364c}
.dv-admin *{box-sizing:border-box}.dv-admin .top{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:22px}
.dv-admin h1{font-size:25px;letter-spacing:-.8px;margin:0}.dv-admin .sub{color:#778398;font-size:13px;margin:5px 0 0}
.dv-admin h2{font-size:17px;letter-spacing:-.3px;margin:0}.dv-admin .panel{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 24px #20304b08;margin-bottom:18px;overflow:hidden}
.dv-admin .panel-head{padding:18px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid var(--line)}
.dv-admin .panel-body{padding:20px}.dv-admin .btn{margin:0;background:var(--blue);border:1px solid transparent;border-radius:9px;color:white;font-size:13px;font-weight:700;padding:9px 13px;line-height:1.4;cursor:pointer;white-space:nowrap;text-decoration:none}
.dv-admin .btn:hover{background:#1d4ed8}.dv-admin .btn.light{background:#fff;border-color:#dce3ed;color:#33445d}.dv-admin .btn.light:hover{background:#f1f5f9}.dv-admin .btn.danger{background:#fff;border-color:#f4d3d3;color:#bd3b3b}.dv-admin .btn.small{padding:6px 10px;font-size:12px}
.dv-admin .btn:disabled{opacity:.5;cursor:not-allowed}.dv-admin form{margin:0}.dv-admin label{font-size:12px;font-weight:700;color:#5a6980;margin:0 0 6px;display:block}
.dv-admin input{font-size:14px;padding:10px 11px;border:1px solid #dce3ed;border-radius:9px;min-width:0;width:100%;background:white}.dv-admin input:focus{outline:2px solid #c8dbff;border-color:#5791f7}
.dv-admin .form-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr auto;gap:12px;align-items:end}.dv-admin .toolbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dv-admin .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}.dv-admin .stat{background:white;border:1px solid var(--line);border-radius:14px;padding:17px 19px}.dv-admin .stat .num{font-size:27px;font-weight:800;line-height:1.2;color:var(--navy)}.dv-admin .stat .lbl{color:#778398;font-size:12px;margin-top:5px}
.dv-admin .table-wrap{overflow-x:auto}.dv-admin table{width:100%;border-collapse:collapse;font-size:13px}.dv-admin th{background:#f9fafc;color:#728096;font-weight:700;font-size:12px}.dv-admin td,.dv-admin th{padding:13px 14px;text-align:left;border-bottom:1px solid #edf0f5;white-space:nowrap;vertical-align:middle}.dv-admin tbody tr:last-child td{border-bottom:0}.dv-admin tbody tr:hover{background:#fbfcff}
.dv-admin .muted{font-size:12px;color:#8490a1}.dv-admin .pill{display:inline-flex;align-items:center;padding:5px 9px;border-radius:99px;font-size:11px;font-weight:800;background:#eef2f7;color:#5c6a7d}.dv-admin .pill.green{background:#e9f8ef;color:#17864e}.dv-admin .pill.red{background:#fff0f0;color:#bd4242}.dv-admin .pill.blue{background:#edf4ff;color:#2563eb}
.dv-admin .name{font-weight:800;color:#25364c}.dv-admin .actions{display:flex;align-items:center;gap:6px;flex-wrap:nowrap}.dv-admin .actions form{display:inline-flex}.dv-admin .mini-link{color:#2563eb;font-weight:700;text-decoration:none;font-size:12px}
.dv-admin .detail{background:#f8fafc;padding:18px 20px;border-top:1px solid var(--line)}.dv-admin .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.dv-admin .url-line{display:flex;gap:8px;align-items:center;margin-top:8px}.dv-admin .url-text{font-size:12px;color:#63748c;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0;background:#fff;border:1px solid #e2e8f0;padding:9px;border-radius:8px}
.dv-admin .edit-grid{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end}.dv-admin .danger-zone{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.dv-admin details>summary{cursor:pointer;color:#2563eb;font-weight:700;font-size:12px;list-style:none}.dv-admin details>summary::-webkit-details-marker{display:none}.dv-admin details[open]>summary{margin-bottom:0}
.dv-admin .empty{text-align:center;padding:36px;color:#8a96a8;font-size:13px}.dv-admin .alert{padding:12px 15px;border-radius:10px;margin-bottom:15px;font-size:13px}.dv-admin .alert.error{background:#fff0f0;color:#a62626}.dv-admin .alert.ok{background:#e9f8ef;color:#187343}.dv-admin .search{max-width:260px}.dv-admin .note{font-size:12px;color:#8390a2;padding:0 20px 14px}.dv-admin .login{max-width:390px;margin:10vh auto}.dv-admin .login .panel-body{padding:28px}.dv-admin .login .btn{width:100%;margin-top:16px}
.dv-admin .history-cell{max-width:290px;white-space:normal;overflow-wrap:anywhere;font-size:12px;color:#64748b}.dv-admin .truncate{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.dv-admin .toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1d3554;color:white;padding:12px 18px;border-radius:10px;box-shadow:0 8px 30px #0002;z-index:99;font-size:13px;display:none}
@media(max-width:800px){.wrap{padding:0 12px}.dv-admin .stats{grid-template-columns:repeat(2,1fr)}.dv-admin .form-grid{grid-template-columns:1fr 1fr}.dv-admin .form-grid button{width:100%}.dv-admin .detail-grid{grid-template-columns:1fr}.dv-admin .edit-grid{grid-template-columns:1fr 1fr}.dv-admin .top{align-items:flex-start}.dv-admin h1{font-size:21px}}
@media(max-width:480px){.dv-admin .form-grid,.dv-admin .edit-grid{grid-template-columns:1fr}.dv-admin .panel-head{padding:15px}.dv-admin .panel-body{padding:15px}.dv-admin .stat{padding:14px}.dv-admin .stat .num{font-size:23px}.dv-admin .search{max-width:170px}.dv-admin .top{flex-wrap:wrap}}
</style>
<div class="dv-admin">
<?php if ($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert ok"><?=h($notice)?></div><?php endif; ?>
<?php if (!logged()): ?>
<section class="login panel"><div class="panel-body">
<h1>차량등록 관리자</h1><p class="sub" style="margin-bottom:24px">관리자 비밀번호로 로그인하세요.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="login"><label for="password">관리자 비밀번호</label><input id="password" type="password" name="password" required autocomplete="current-password"><button class="btn" type="submit">로그인</button></form>
</div></section>
<?php else:
$recipients=db()->query('SELECT * FROM dv_recipients ORDER BY id DESC')->fetchAll();
$requests=db()->query('SELECT q.*,r.label FROM dv_requests q JOIN dv_recipients r ON r.id=q.recipient_id ORDER BY q.id DESC LIMIT 200')->fetchAll();
$active=0; foreach($recipients as $r){if($r['active'])$active++;}
$accepted=0;$failed=0;foreach($requests as $q){if($q['send_status']==='accepted')$accepted++;if($q['send_status']==='failed')$failed++;}
?>
<div class="top"><div><h1>새벽배송 차량등록 관리</h1><p class="sub">수신인 · 신청 링크 · 알림톡 발송 현황</p></div><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="logout"><button class="btn light" type="submit">로그아웃</button></form></div>
<div class="stats">
<div class="stat"><div class="num"><?=count($recipients)?></div><div class="lbl">전체 수신인</div></div>
<div class="stat"><div class="num"><?=$active?></div><div class="lbl">사용 중인 링크</div></div>
<div class="stat"><div class="num"><?=count($requests)?></div><div class="lbl">최근 신청 (최대 200건)</div></div>
<div class="stat"><div class="num"><?=$failed?></div><div class="lbl">최근 API 접수 실패</div></div>
</div>
<section class="panel"><div class="panel-head"><h2>수신인 관리 <span class="muted">· <?=count($recipients)?>명</span></h2><div class="toolbar"><input class="search" type="search" id="recipientSearch" placeholder="구분명·이름 검색" aria-label="수신인 검색"><button type="button" class="btn" id="addToggle">+ 수신인 추가</button></div></div>
<div class="panel-body" id="addPanel" hidden style="background:#f8fafc;border-bottom:1px solid #e6eaf1"><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="add"><div><label>구분명</label><input name="label" required maxlength="80" placeholder="예: 101동 1001호"></div><div><label>수신인 이름</label><input name="name" required maxlength="80" placeholder="이름"></div><div><label>휴대폰번호</label><input name="phone" required inputmode="tel" placeholder="01012345678"></div><button type="submit" class="btn">등록하기</button></form></div>
<div class="table-wrap"><table><thead><tr><th>구분명 / 수신인</th><th>연락처</th><th>상태</th><th>단축 URL</th><th>관리</th></tr></thead><tbody>
<?php foreach($recipients as $r): $link=url('index.php?key='.$r['token']); $short=(string)($r['short_url']??''); $rid=(int)$r['id']; ?>
<tr class="recipient-row" data-search="<?=h(mb_strtolower($r['label'].' '.$r['recipient_name'].' '.$r['recipient_phone']))?>">
<td><div class="name"><?=h($r['label'])?></div><div class="muted"><?=h($r['recipient_name'])?></div></td>
<td><?=h($r['recipient_phone'])?></td><td><span class="pill <?=$r['active']?'green':'red'?>"><?=$r['active']?'사용중':'중지'?></span></td>
<td><?php if($short): ?><button type="button" class="btn light small copy-link" data-link="<?=h($short)?>">단축 URL 복사</button><?php else: ?><span class="muted">미생성</span><?php endif; ?></td>
<td><div class="actions"><button type="button" class="btn light small copy-link" data-link="<?=h($link)?>">원본 복사</button><button type="button" class="btn light small recipient-detail-toggle" data-id="<?=$rid?>" aria-expanded="false">상세 · 수정</button></div></td>
</tr>
<tr class="recipient-detail-row" id="recipient-detail-<?=$rid?>" hidden><td colspan="5" style="padding:0;white-space:normal"><div class="detail"><div class="detail-grid"><div><label>배송기사 신청 URL</label><div class="url-line"><span class="url-text" title="<?=h($link)?>"><?=h($link)?></span><button type="button" class="btn light small copy-link" data-link="<?=h($link)?>">복사</button></div><div style="margin-top:14px"><label>Bitly 단축 URL</label><?php if($short): ?><div class="url-line"><a class="url-text" href="<?=h($short)?>" target="_blank" rel="noopener noreferrer"><?=h($short)?></a><button type="button" class="btn small copy-link" data-link="<?=h($short)?>">복사</button></div><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="shorten"><input type="hidden" name="id" value="<?=$rid?>"><button type="submit" class="btn light">Bitly 단축 URL 생성</button></form><?php endif; ?></div></div>
<div><form method="post" class="edit-grid"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?=$rid?>"><div><label>구분명</label><input name="label" value="<?=h($r['label'])?>" required maxlength="80"></div><div><label>이름</label><input name="name" value="<?=h($r['recipient_name'])?>" required maxlength="80"></div><div><label>휴대폰번호</label><input name="phone" value="<?=h($r['recipient_phone'])?>" required></div><button class="btn" type="submit">저장</button></form>
<div class="danger-zone"><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$rid?>"><button type="submit" class="btn light small"><?=$r['active']?'사용 중지':'사용 재개'?></button></form><form method="post" onsubmit="return confirm('기존 원본 URL이 무효화되고 Bitly 단축 URL도 사용할 수 없게 됩니다. 계속하시겠습니까?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="rotate"><input type="hidden" name="id" value="<?=$rid?>"><button type="submit" class="btn danger small">신청 링크 재발급</button></form></div></div></div></div></td></tr>
<?php endforeach; ?>
<?php if(!$recipients): ?><tr><td colspan="5" class="empty">등록된 수신인이 없습니다. 오른쪽 위에서 추가해 주세요.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="panel" id="requests"><div class="panel-head"><h2>최근 신청내역</h2><div class="toolbar"><input class="search" type="search" id="requestSearch" placeholder="마켓·차량번호·수신인 검색" aria-label="신청내역 검색"></div></div>
<div class="table-wrap"><table><thead><tr><th>접수일시</th><th>수신인</th><th>마켓</th><th>방문예정일</th><th>차량번호</th><th>알림톡 API</th><th>관리</th></tr></thead><tbody>
<?php foreach($requests as $q): $status=$q['send_status']; $qid=(int)$q['id']; $badge=$status==='accepted'?'blue':($status==='failed'?'red':''); $label=$status==='accepted'?'발송접수':($status==='failed'?'접수실패':'접수대기'); ?>
<tr class="request-row" data-search="<?=h(mb_strtolower($q['label'].' '.$q['market'].' '.$q['vehicle_no']))?>"><td><?=h($q['created_at'])?></td><td class="name"><?=h($q['label'])?></td><td><?=h($q['market'])?></td><td><?=h($q['visit_date'])?></td><td><?=h($q['vehicle_no'])?></td><td><span class="pill <?=$badge?>"><?=$label?></span><?php if($status==='failed'): ?><div class="muted" title="<?=h($q['send_message']??'')?>">HTTP <?=h($q['send_http']??'-')?></div><?php endif; ?></td><td><div class="actions"><a class="btn light small" href="admin.php?history=<?=$qid?>#history">이력</a><form method="post" onsubmit="return confirm('알림톡을 재발송할까요?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="retry"><input type="hidden" name="id" value="<?=$qid?>"><button type="submit" class="btn light small">재발송</button></form></div></td></tr>
<?php endforeach; ?>
<?php if(!$requests): ?><tr><td colspan="7" class="empty">아직 접수된 신청이 없습니다.</td></tr><?php endif; ?>
</tbody></table></div><div class="note">발송접수는 비즈뿌리오 API 접수 성공이며 실제 수신 완료를 뜻하지 않습니다.</div></section>
<?php if(isset($_GET['history'])): $hid=max(0,(int)$_GET['history']); $stmt=db()->prepare('SELECT a.* FROM dv_send_attempts a JOIN dv_requests q ON q.id=a.request_id WHERE q.id=? ORDER BY a.id DESC LIMIT 100'); $stmt->execute([$hid]); $attempts=$stmt->fetchAll(); ?>
<section class="panel" id="history"><div class="panel-head"><h2>발송 시도 이력 #<?=$hid?></h2><a href="admin.php#requests" class="btn light small">닫기</a></div><div class="table-wrap"><table><thead><tr><th>시도시각</th><th>결과</th><th>HTTP</th><th>응답코드</th><th>refKey</th><th>messageKey</th><th>응답 메시지</th></tr></thead><tbody>
<?php foreach($attempts as $a): ?><tr><td><?=h($a['created_at'])?></td><td><span class="pill <?=$a['send_status']==='accepted'?'blue':'red'?>"><?=$a['send_status']==='accepted'?'API 접수':'접수 실패'?></span></td><td><?=h($a['send_http'])?></td><td><?=h($a['result_code'])?></td><td><?=h($a['ref_key'])?></td><td><?=h($a['message_key'])?></td><td class="history-cell"><?=h($a['send_message'])?></td></tr><?php endforeach; ?>
<?php if(!$attempts): ?><tr><td colspan="7" class="empty">발송 시도 이력이 없습니다.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php endif; ?>
<div class="toast" id="dvToast" role="status" aria-live="polite"></div>
</div>
<script>
(function(){
const toast=document.getElementById('dvToast');let hideTimer;
function notify(message){toast.textContent=message;toast.style.display='block';clearTimeout(hideTimer);hideTimer=setTimeout(()=>toast.style.display='none',2200)}
async function copy(value){try{if(!navigator.clipboard||!navigator.clipboard.writeText)throw Error('unavailable');await navigator.clipboard.writeText(value);notify('링크를 복사했습니다.');return}catch(e){}
const t=document.createElement('textarea');t.value=value;t.style.position='fixed';t.style.opacity='0';document.body.appendChild(t);t.focus();t.select();let ok=false;try{ok=document.execCommand('copy')}catch(e){}t.remove();if(ok)notify('링크를 복사했습니다.');else window.prompt('아래 링크를 직접 복사하세요.',value)}
document.querySelectorAll('.copy-link').forEach(b=>b.addEventListener('click',()=>copy(b.dataset.link||'')));
const add=document.getElementById('addToggle'),panel=document.getElementById('addPanel');if(add&&panel)add.addEventListener('click',()=>{panel.hidden=!panel.hidden;add.textContent=panel.hidden?'+ 수신인 추가':'닫기';if(!panel.hidden)panel.querySelector('input[name="label"]').focus()});
document.querySelectorAll('.recipient-detail-toggle').forEach(b=>b.addEventListener('click',()=>{const row=document.getElementById('recipient-detail-'+b.dataset.id);if(!row)return;row.hidden=!row.hidden;b.setAttribute('aria-expanded',String(!row.hidden));b.textContent=row.hidden?'상세 · 수정':'접기'}));
function filter(inputSelector,rowSelector){const input=document.querySelector(inputSelector);if(!input)return;input.addEventListener('input',()=>{const v=input.value.trim().toLocaleLowerCase();document.querySelectorAll(rowSelector).forEach(row=>{const visible=(row.dataset.search||'').toLocaleLowerCase().includes(v);row.hidden=!visible;if(row.classList.contains('recipient-row')&&!visible){const detail=row.nextElementSibling;if(detail&&detail.classList.contains('recipient-detail-row'))detail.hidden=true}})})}
filter('#recipientSearch','.recipient-row');filter('#requestSearch','.request-row');
})();
</script>
<?php layout_end(); ?>
