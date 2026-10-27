<?php require __DIR__.'/lib.php';$no=$_SESSION['apple_order_success']??null;unset($_SESSION['apple_order_success']);layout_start('주문 접수 완료');?><div class="card"><h1>🍎 주문이 접수되었습니다</h1><?php if($no):?><p class="notice">주문번호: <strong><?=h($no)?></strong></p><?php else:?><p>주문 완료 화면입니다.</p><?php endif;?><p>농장에서 주문을 확인한 후 배송을 진행합니다.</p><a class="btn" href="index.php">새 주문하기</a></div><?php $payment=cfg()['payment_account']??[]; if(!empty($payment['number'])): ?>
<section class="card" style="margin-top:16px;border:1px solid #b8d9bc;background:#f8fcf8" aria-label="주문대금 입금 안내">
<h2 style="margin:0 0 12px;font-size:21px">💳 주문대금 입금 안내</h2>
<p style="margin:5px 0">은행: <strong><?=h($payment['bank']??'')?></strong></p>
<p style="margin:5px 0">예금주: <strong><?=h($payment['holder']??'')?></strong></p>
<div class="actions" style="align-items:center;margin:12px 0"><strong id="paymentAccount" style="font-size:21px;letter-spacing:.3px"><?=h($payment['number'])?></strong><button type="button" class="secondary" onclick="copyPaymentAccount(this)">📋 계좌번호 복사</button></div>
<p class="muted" style="font-size:13px;margin-bottom:0">주문 접수 후 위 계좌로 주문대금을 입금해 주세요. 계좌번호 복사 시 하이픈(-)은 제외됩니다.</p>
</section>
<script>
async function copyPaymentAccount(button){
 const number=<?=json_encode(preg_replace('/\D+/', '', (string)$payment['number']), JSON_UNESCAPED_UNICODE)?>;
 try{
  if(navigator.clipboard && window.isSecureContext){await navigator.clipboard.writeText(number)}
  else {const el=document.createElement('textarea');el.value=number;el.style.position='fixed';el.style.opacity='0';document.body.appendChild(el);el.focus();el.select();const ok=document.execCommand('copy');el.remove();if(!ok)throw new Error('copy failed')}
  const old=button.textContent;button.textContent='✓ 복사 완료';setTimeout(()=>button.textContent=old,2000);
 }catch(e){window.prompt('아래 계좌번호를 복사해 주세요.',number)}
}
</script>
<?php endif; ?>
<?php layout_end();
