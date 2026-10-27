<?php
require __DIR__ . '/lib.php';
$error = '';
$success = '';
$values = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = phone_clean((string)($_POST['phone'] ?? ''));
        $postcode = trim((string)($_POST['postcode'] ?? ''));
        $road = trim((string)($_POST['road_address'] ?? ''));
        $detail = trim((string)($_POST['detail_address'] ?? ''));
        $code = (string)($_POST['product'] ?? '');
        $qty = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
        $request = trim((string)($_POST['delivery_request'] ?? ''));
        $referrer = trim((string)($_POST['referrer'] ?? ''));
        $products = cfg()['products'];
        $customName = trim((string)($_POST['custom_product_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) throw new RuntimeException('수령자 성명을 확인해 주세요.');
        if (!preg_match('/^0\d{8,10}$/', $phone)) throw new RuntimeException('연락처를 정확히 입력해 주세요.');
        if (!preg_match('/^\d{5}$/', $postcode) || $road === '' || mb_strlen($road) > 255) throw new RuntimeException('주소검색으로 도로명주소를 선택해 주세요.');
        if ($detail === '' || mb_strlen($detail) > 255) throw new RuntimeException('상세주소(동·호수 등)를 입력해 주세요.');
        if ($code !== 'other' && !isset($products[$code])) throw new RuntimeException('상품종류를 선택해 주세요.');
        if ($code === 'other' && ($customName === '' || mb_strlen($customName) > 120)) throw new RuntimeException('기타상품명을 120자 이내로 입력해 주세요.');
        if ($qty === false || $qty < 1 || $qty > (int)cfg()['max_quantity']) throw new RuntimeException('주문 수량을 확인해 주세요.');
        if (mb_strlen($referrer) > 80) throw new RuntimeException('추천인은 80자 이내로 입력해 주세요.');
        if (mb_strlen($request) > 1000) throw new RuntimeException('배송요청은 1000자 이내로 입력해 주세요.');
        $product = $code === 'other' ? ['name' => $customName, 'price' => 0] : $products[$code];
        $no = date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
        $st = db()->prepare('INSERT INTO apple_orders(order_no,recipient_name,phone,postcode,road_address,detail_address,product_code,product_name,unit_price,quantity,delivery_request,referrer) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$no, $name, $phone, $postcode, $road, $detail, $code, $product['name'], max(0, (int)$product['price']), $qty, $request, $referrer]);
        // 주문 저장이 성공한 경우에만 관리자 알림톡을 시도합니다. 발송 실패는 주문접수를 취소하지 않습니다.
        try {
            require_once __DIR__ . '/order_alimtalk.php';
            apple_notify_order($no);
        } catch (Throwable $notifyError) {
            error_log('[apple_order] alimtalk: ' . $notifyError->getMessage());
        }
        $_SESSION['apple_order_success'] = $no;
        header('Location: complete.php');
        exit;
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? '주문 저장 중 오류가 발생했습니다. 관리자에게 문의하세요.' : $e->getMessage();
    }
}
layout_start(cfg()['site_name']);
?>
<div class="card">
    <h1>🍎 <?= h(cfg()['site_name']) ?></h1>
    <p class="muted">농장에서 신선하게 보내드립니다. * 표시는 필수 입력사항입니다.</p>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <form method="post" id="orderForm"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <div class="grid">
            <div><label for="name">수령자 성명 *</label><input id="name" name="name" maxlength="80" required value="<?= h($values['name'] ?? '') ?>"></div>
            <div><label for="phone">연락처 *</label><input id="phone" name="phone" type="tel" inputmode="tel" maxlength="15" placeholder="01012345678" required value="<?= h($values['phone'] ?? '') ?>"></div>
        </div>
        <label>도로명주소 * <small>(카카오 주소검색)</small></label>
        <div class="actions" style="margin:0 0 8px"><input id="postcode" name="postcode" readonly required placeholder="우편번호 검색=>" style="max-width:170px" value="<?= h($values['postcode'] ?? '') ?>"><button type="button" onclick="findAddress()">주소 검색</button></div><input id="road_address" name="road_address" readonly required placeholder="주소검색을 눌러 선택해 주세요" value="<?= h($values['road_address'] ?? '') ?>"><label for="detail_address">상세주소 *</label><input id="detail_address" name="detail_address" required maxlength="255" placeholder="아파트명, 동·호수 등" value="<?= h($values['detail_address'] ?? '') ?>">
        <div class="grid">
            <div><label for="product">상품종류 *</label><select id="product" name="product" required>
                    <option value="">상품을 선택하세요</option><?php foreach (cfg()['products'] as $key => $p): ?><option value="<?= h($key) ?>" <?= ($values['product'] ?? '') === $key ? 'selected' : '' ?>><?= h($p['name']) ?><?= ((int)$p['price'] > 0 ? ' · ' . number_format((int)$p['price']) . '원' : '') ?></option><?php endforeach; ?><option value="other" <?= ($values['product'] ?? '') === 'other' ? 'selected' : '' ?>>기타상품 (직접입력)</option>
                </select>
                <div id="customProductWrap" style="display:none;margin-top:10px"><label for="custom_product_name">기타상품명 *</label><input id="custom_product_name" name="custom_product_name" maxlength="120" placeholder="예: 선물용 부사 10kg" value="<?= h($values['custom_product_name'] ?? '') ?>"></div>
            </div>
            <div><label for="quantity">수량 *</label><input type="number" id="quantity" name="quantity" min="1" max="<?= (int)cfg()['max_quantity'] ?>" value="<?= h($values['quantity'] ?? '1') ?>" required></div>
        </div>
        <label for="referrer">추천인 (선택)</label><input id="referrer" name="referrer" maxlength="80" placeholder="추천인 성명을 입력해 주세요" value="<?= h($values['referrer'] ?? '') ?>">
        <label for="delivery_request">배송요청내용 (선택)</label><textarea id="delivery_request" name="delivery_request" maxlength="1000" rows="3" placeholder="예: 부재 시 문 앞에 놓아주세요"><?= h($values['delivery_request'] ?? '') ?></textarea>
        <p class="muted" style="font-size:13px">입력하신 개인정보는 주문 접수 및 배송처리에 사용됩니다. 주문 전 개인정보 처리 안내를 확인해 주세요.</p><button type="submit" id="submitBtn" style="width:100%">📝주문내용 확인 및 접수</button>
    </form>
</div>
<div id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;overflow:auto;padding:20px">
    <div style="max-width:520px;margin:5vh auto;background:white;color:#222;border-radius:14px;padding:22px;box-shadow:0 15px 50px #0004">
        <h2 id="confirmTitle">🍎 주문내용 최종 확인</h2>
        <p>배송주소와 상품·수량을 확인해 주세요.</p>
        <div id="confirmDetails" style="line-height:1.8;overflow-wrap:anywhere"></div>
        <div class="actions" style="margin-top:20px;display:flex;gap:10px"><button type="button" id="editOrder" class="secondary" style="flex:1">수정하기</button><button type="button" id="finalOrder" style="flex:1">✔이대로 접수하기</button></div>
    </div>
</div>
<?php $payment = cfg()['payment_account'] ?? [];
if (!empty($payment['number'])): ?>
    <section class="card" style="margin-top:16px;border:1px solid #b8d9bc;background:#f8fcf8" aria-label="주문대금 입금 안내">
        <h2 style="margin:0 0 12px;font-size:21px">💳 주문대금 입금 안내</h2>
        <p style="margin:5px 0">은행: <strong><?= h($payment['bank'] ?? '') ?></strong></p>
        <p style="margin:5px 0">예금주: <strong><?= h($payment['holder'] ?? '') ?></strong></p>
        <div class="actions" style="align-items:center;margin:12px 0"><strong id="paymentAccount" style="font-size:21px;letter-spacing:.3px"><?= h($payment['number']) ?></strong><button type="button" class="secondary" onclick="copyPaymentAccount(this)">📋 계좌번호 복사</button></div>
        <p class="muted" style="font-size:13px;margin-bottom:0">주문 접수 후 위 계좌로 주문대금을 입금해 주세요. 계좌번호 복사 시 하이픈(-)은 제외됩니다.</p>
    </section>
    <script>
        async function copyPaymentAccount(button) {
            const number = <?= json_encode(preg_replace('/\D+/', '', (string)$payment['number']), JSON_UNESCAPED_UNICODE) ?>;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(number)
                } else {
                    const el = document.createElement('textarea');
                    el.value = number;
                    el.style.position = 'fixed';
                    el.style.opacity = '0';
                    document.body.appendChild(el);
                    el.focus();
                    el.select();
                    const ok = document.execCommand('copy');
                    el.remove();
                    if (!ok) throw new Error('copy failed')
                }
                const old = button.textContent;
                button.textContent = '✓ 복사 완료';
                setTimeout(() => button.textContent = old, 2000);
            } catch (e) {
                window.prompt('아래 계좌번호를 복사해 주세요.', number)
            }
        }
    </script>
<?php endif; ?>
<script src="https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
    const productSelect = document.getElementById('product');
    const customWrap = document.getElementById('customProductWrap');
    const customInput = document.getElementById('custom_product_name');

    function toggleCustomProduct() {
        const other = productSelect.value === 'other';
        customWrap.style.display = other ? 'block' : 'none';
        customInput.required = other;
        if (!other) customInput.value = '';
    }
    productSelect.addEventListener('change', toggleCustomProduct);
    toggleCustomProduct();

    function findAddress() {
        if (!window.daum?.Postcode) {
            alert('주소검색을 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.');
            return;
        }
        new daum.Postcode({
            oncomplete: function(data) {
                document.getElementById('postcode').value = data.zonecode;
                let address = data.roadAddress || data.address;
                let extra = [];
                if (data.bname && /[동|로|가]$/.test(data.bname)) extra.push(data.bname);
                if (data.buildingName && data.apartment === 'Y') extra.push(data.buildingName);
                document.getElementById('road_address').value = address + (extra.length ? ' (' + extra.join(', ') + ')' : '');
                document.getElementById('detail_address').focus();
            }
        }).open();
    }
    const orderForm = document.getElementById('orderForm'),
        modal = document.getElementById('confirmModal');
    let submitting = false;

    function addConfirmLine(parent, label, value) {
        const row = document.createElement('div');
        row.style.padding = '6px 0';
        row.style.borderBottom = '1px solid #eee';
        const name = document.createElement('strong');
        name.textContent = label + ' : ';
        const val = document.createElement('span');
        val.textContent = value || '없음';
        row.append(name, val);
        parent.append(row);
    }
    orderForm.addEventListener('submit', function(event) {
        event.preventDefault();
        if (submitting) return;
        if (!orderForm.reportValidity()) return;
        const code = productSelect.value;
        if (code === 'other' && !customInput.value.trim()) {
            customInput.setCustomValidity('기타상품명을 입력해 주세요.');
            customInput.reportValidity();
            return;
        }
        const details = document.getElementById('confirmDetails');
        details.replaceChildren();
        const get = id => document.getElementById(id).value.trim();
        const productName = code === 'other' ? customInput.value.trim() : productSelect.options[productSelect.selectedIndex].text.trim();
        addConfirmLine(details, '수령자', get('name'));
        addConfirmLine(details, '연락처', get('phone'));
        addConfirmLine(details, '배송주소', '(' + get('postcode') + ') ' + get('road_address') + ' ' + get('detail_address'));
        addConfirmLine(details, '상품종류', productName);
        addConfirmLine(details, '수량', get('quantity') + '개');
        addConfirmLine(details, '추천인', get('referrer'));
        addConfirmLine(details, '배송요청', get('delivery_request'));
        modal.style.display = 'block';
        document.getElementById('editOrder').focus();
    });
    customInput.addEventListener('input', () => customInput.setCustomValidity(''));
    document.getElementById('editOrder').addEventListener('click', () => {
        modal.style.display = 'none';
        document.getElementById('submitBtn').focus();
    });
    document.getElementById('finalOrder').addEventListener('click', () => {
        if (submitting) return;
        submitting = true;
        const btn = document.getElementById('finalOrder');
        btn.disabled = true;
        btn.textContent = '접수 중...';
        document.getElementById('submitBtn').disabled = true;
        HTMLFormElement.prototype.submit.call(orderForm);
    });
</script>
<?php layout_end();
