<?php
$signatureUrl = static function (?string $path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }
    return rtrim((string)cfg('base_url'), '/') . '/' . ltrim($path, '/');
};
?>
<div class="contract" id="contractDocument">
    <h1><?=e($c['title'])?></h1>

    <h2>1. 부동산의 표시</h2>
    <table>
        <tr>
            <th>소재지</th>
            <td colspan="3"><?=e($c['property_address'])?> <?=e($c['property_detail'])?></td>
        </tr>
        <tr>
            <th>층수·면적</th>
            <td><?=e($c['floor_info'])?> / 전용 <?=e($c['area_m2'])?>㎡</td>
            <th>용도</th>
            <td><?=e($c['use_type'])?></td>
        </tr>
    </table>

    <h2>2. 계약 조건</h2>
    <table>
        <tr>
            <th>보증금</th>
            <td><?=money($c['deposit'])?></td>
            <th>월차임</th>
            <td><?=money($c['monthly_rent'])?></td>
        </tr>
        <tr>
            <th>입금계좌</th>
            <td colspan="3"><?=e($c['rent_account'])?></td>
        </tr>
        <tr>
            <th>명도일</th>
            <td><?=e($c['handover_date'])?></td>
            <th>임대기간</th>
            <td><?=e($c['start_date'])?> ~ <?=e($c['end_date'])?></td>
        </tr>
    </table>

    <div class="terms"><?=e($c['terms'])?></div>

    <?php if (trim((string)$c['special_terms']) !== ''): ?>
        <h2>추가 특약사항</h2>
        <div class="terms"><?=e($c['special_terms'])?></div>
    <?php endif; ?>

    <h2>3. 계약 당사자</h2>
    <table>
        <tr>
            <th>임대인</th>
            <td>
                <?=e($c['lessor_name'])?> (생년월일 <?=e($c['lessor_birth'])?>)<br>
                <?=e($c['lessor_address'])?><br>
                <?=e($c['lessor_phone'])?>
            </td>
            <th>임차인</th>
            <td>
                <?=e($c['lessee_name'])?> (생년월일 <?=e($c['lessee_birth'])?>)<br>
                <?=e($c['lessee_address'])?><br>
                <?=e($c['lessee_phone'])?>
            </td>
        </tr>
        <?php if (!empty($c['agent_name'])): ?>
            <tr>
                <th>대리인</th>
                <td colspan="3"><?=e($c['agent_name'])?> / <?=e($c['agent_phone'])?></td>
            </tr>
        <?php endif; ?>
    </table>

    <h2>4. 전자서명</h2>
    <div class="signature-box">
        <div>
            <b>임대인 <?=e($c['lessor_name'])?></b><br>
            <?php if (!empty($c['lessor_signature'])): ?>
                <img class="signature-img"
                     src="<?=e($signatureUrl($c['lessor_signature']))?>"
                     alt="임대인 서명"><br>
                <?=e($c['lessor_signed_at'])?> 서명
            <?php else: ?>
                미서명
            <?php endif; ?>
        </div>

        <div>
            <b>임차인 <?=e($c['lessee_name'])?></b><br>
            <?php if (!empty($c['lessee_signature'])): ?>
                <img class="signature-img"
                     src="<?=e($signatureUrl($c['lessee_signature']))?>"
                     alt="임차인 서명"><br>

                <div style="margin-top:8px;padding:8px 10px;border:1px solid #999;background:#f7f7f7;font-size:11px;line-height:1.55;word-break:break-all;">
                    <div>
                        <strong>전자서명 완료일시:</strong>
                        <?=e($c['lessee_signed_at'] ?: '-')?>
                        <?php if (!empty($c['lessee_signed_at'])): ?>(KST)<?php endif; ?>
                    </div>

                    <div>
                        <strong>서명 접속 IP:</strong>
                        <?=e($c['lessee_ip'] ?? '-')?>
                    </div>

                    <?php if (!empty($c['lessee_user_agent'])): ?>
                        <div>
                            <strong>접속환경:</strong>
                            <?=e($c['lessee_user_agent'])?>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:6px;padding-top:5px;border-top:1px dotted #aaa;font-size:10px;color:#444;">
                        위 정보는 임차인이 전자계약 내용을 확인하고 전자서명을 완료한 시점의 시스템 기록입니다.
                    </div>
                </div>
            <?php else: ?>
                미서명
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($c['document_hash'])): ?>
        <p class="small">문서 확인값: <?=e($c['document_hash'])?></p>
    <?php endif; ?>
</div>
