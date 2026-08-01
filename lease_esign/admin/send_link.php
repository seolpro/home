<?php
require_once dirname(__DIR__) . '/lib.php';

admin_required();

$id = (int)($_POST['id'] ?? 0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('잘못된 요청입니다.');
    }

    verify_csrf();

    if ($id < 1) {
        throw new RuntimeException('계약서 번호가 올바르지 않습니다.');
    }

    $party = (($_POST['party'] ?? '') === 'lessor') ? 'lessor' : 'lessee';

    $stmt = db()->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $contract = $stmt->fetch();

    if (!$contract) {
        throw new RuntimeException('계약서를 찾을 수 없습니다.');
    }

    if ($party === 'lessee' && empty($contract['lessor_signed_at'])) {
        throw new RuntimeException('임대인 서명이 먼저 완료되어야 합니다.');
    }

    if ($party === 'lessor') {
        $token = (string)($_SESSION['new_lessor_token'] ?? '');
        $phone = (string)($contract['lessor_phone'] ?? '');
        $name = (string)($contract['lessor_name'] ?? '임대인');
        $partyLabel = '임대인';
        $hashColumn = 'lessor_token_hash';
        $sessionKey = 'new_lessor_token';
    } else {
        $token = (string)($_SESSION['new_lessee_token'] ?? '');
        $phone = (string)($contract['lessee_phone'] ?? '');
        $name = (string)($contract['lessee_name'] ?? '임차인');
        $partyLabel = '임차인';
        $hashColumn = 'lessee_token_hash';
        $sessionKey = 'new_lessee_token';
    }

    /*
     * 관리자 재로그인·세션 만료 등으로 링크 원문이 사라진 경우
     * 해당 당사자의 링크만 자동 재발급하고 즉시 문자 발송합니다.
     */
    $autoRegenerated = false;
    if ($token === '') {
        $token = token();

        $sql = "UPDATE contracts
                SET {$hashColumn} = ?,
                    token_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY),
                    updated_at = NOW()
                WHERE id = ?";

        $update = db()->prepare($sql);
        $update->execute([
            hash_token($token),
            (int)cfg('security.token_days'),
            $id
        ]);

        $_SESSION[$sessionKey] = $token;
        $autoRegenerated = true;

        audit($id, 'admin', 'signature_token_auto_regenerated', [
            'party' => $party
        ]);
    }

    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^01[0-9]{8,9}$/', $phone)) {
        throw new RuntimeException($partyLabel . ' 연락처가 올바르지 않습니다: ' . $phone);
    }

    $baseUrl = rtrim((string)cfg('base_url'), '/');
    if ($baseUrl === '') {
        throw new RuntimeException('config.php의 base_url 설정이 없습니다.');
    }

    $signUrl = $baseUrl
        . '/sign.php?party=' . rawurlencode($party)
        . '&token=' . rawurlencode($token);

    $message = "[임대차 전자계약]\n"
        . $name . "님, 계약 내용을 확인하고 서명해 주세요.\n\n"
        . $signUrl . "\n\n"
        . "서명 링크는 다른 사람에게 전달하지 마세요.";

    $result = send_sms($phone, $message, $name);
    sms_log($id, $phone, $message, $result);

    audit($id, 'admin', 'signature_link_sent', [
        'party' => $party,
        'phone' => $phone,
        'auto_regenerated' => $autoRegenerated,
        'sms_ok' => !empty($result['ok']),
        'result' => $result
    ]);

    if (!empty($result['ok'])) {
        $_SESSION['flash'] = $partyLabel . '에게 서명 링크 문자를 발송했습니다.'
            . ($autoRegenerated ? ' 새 링크가 자동 발급되었습니다.' : '');
    } else {
        $detail = $result['message']
            ?? $result['error']
            ?? '문자 설정과 뿌리오 응답을 확인하세요.';

        if (!empty($result['api_code'])) {
            $detail .= ' (뿌리오 코드: ' . $result['api_code'] . ')';
        }

        $_SESSION['flash'] = '문자 미발송: ' . $detail;
    }
} catch (Throwable $e) {
    $_SESSION['flash'] = '문자 발송 오류: ' . $e->getMessage();
}

header('Location: view.php?id=' . $id);
exit;
