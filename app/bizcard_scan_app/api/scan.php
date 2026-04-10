<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('잘못된 요청입니다.');
    }

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        throw new RuntimeException('명함 이미지를 업로드해 주세요.');
    }

    if (empty($_FILES['image']['tmp_name'])) {
        throw new RuntimeException('촬영한 이미지 파일을 전달받지 못했습니다.');
    }

    $saved = save_uploaded_image($_FILES['image']);

    $config = app_config();
    $provider = (string)($config['ocr_provider'] ?? 'mock');

    if ($provider === 'google_vision') {
        $ocr = call_google_vision_ocr($saved['path']);
    } else {
        $ocr = mock_ocr($saved['path']);
    }

    $contact = parse_business_card_text((string)$ocr['text']);

    if (empty($config['keep_uploaded_files']) && is_file($saved['path'])) {
        @unlink($saved['path']);
    }

    json_response([
        'success' => true,
        'message' => 'OK',
        'ocr_text' => $ocr['text'],
        'contact' => $contact,
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => $e->getMessage(),
        'files' => $_FILES ?? [],
        'post' => $_POST ?? [],
    ], 400);
}