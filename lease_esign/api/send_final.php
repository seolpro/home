<?php
require_once dirname(__DIR__) . '/lib.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['admin'])) {
    json_out([
        'ok' => false,
        'message' => '관리자 로그인이 필요합니다.'
    ], 403);
}

try {
    $id = (int)($_POST['id'] ?? 0);
    $party = (string)($_POST['party'] ?? 'lessee');

    if ($id < 1) {
        throw new RuntimeException('계약서 번호가 올바르지 않습니다.');
    }

    if (!in_array($party, ['lessor', 'lessee'], true)) {
        throw new RuntimeException('발송 대상을 확인할 수 없습니다.');
    }

    $stmt = db()->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $contract = $stmt->fetch();

    if (!$contract) {
        throw new RuntimeException('계약서를 찾을 수 없습니다.');
    }

    if (empty($contract['final_pdf'])) {
        throw new RuntimeException('먼저 최종 PDF를 생성·저장하세요.');
    }

    if (empty($contract['document_hash'])) {
        throw new RuntimeException('문서 확인값이 없습니다. PDF를 다시 생성해 주세요.');
    }

    if ($party === 'lessor') {
        $recipientName = trim((string)($contract['lessor_name'] ?? ''));
        $recipientPhone = preg_replace('/[^0-9]/', '', (string)($contract['lessor_phone'] ?? ''));
        $partyLabel = '임대인';
    } else {
        $recipientName = trim((string)($contract['lessee_name'] ?? ''));
        $recipientPhone = preg_replace('/[^0-9]/', '', (string)($contract['lessee_phone'] ?? ''));
        $partyLabel = '임차인';
    }

    if ($recipientName === '') {
        $recipientName = $partyLabel;
    }

    if (!preg_match('/^01[0-9]{8,9}$/', $recipientPhone)) {
        throw new RuntimeException($partyLabel . ' 연락처가 올바르지 않습니다.');
    }

    $baseUrl = rtrim((string)cfg('base_url'), '/');
    if ($baseUrl === '') {
        throw new RuntimeException('config.php의 base_url 설정을 확인하세요.');
    }

    $appKey = (string)cfg('security.app_key');
    if ($appKey === '') {
        throw new RuntimeException('config.php의 security.app_key 설정을 확인하세요.');
    }

    $downloadKey = hash_hmac(
        'sha256',
        (string)$id . '|' . (string)$contract['document_hash'],
        $appKey
    );

    $url = $baseUrl
        . '/download.php?id=' . rawurlencode((string)$id)
        . '&key=' . rawurlencode($downloadKey);

    $message =
        "[임대차계약 완료]\n"
        . $recipientName . "님, 서명 완료된 최종 임대차계약서 PDF입니다.\n\n"
        . $url . "\n\n"
        . "안전하게 보관해 주세요.";

    $result = send_sms($recipientPhone, $message, $recipientName);

    sms_log($id, $recipientPhone, $message, $result);

    audit(
        $id,
        'admin',
        'final_pdf_link_sent',
        [
            'party' => $party,
            'recipient' => $recipientPhone,
            'sms_ok' => (bool)($result['ok'] ?? false),
            'result' => $result
        ]
    );

    $ok = (bool)($result['ok'] ?? false);

    json_out([
        'ok' => $ok,
        'message' => $ok
            ? $partyLabel . '에게 최종 PDF 링크 문자를 발송했습니다.'
            : $partyLabel . ' 문자 발송에 실패했습니다. 문자 설정과 발송 로그를 확인하세요.'
    ]);

} catch (Throwable $e) {
    json_out([
        'ok' => false,
        'message' => $e->getMessage()
    ], 400);
}
