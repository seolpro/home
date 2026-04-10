<?php
return [
    'app_name' => 'BizCard Contact Saver',
    'base_url' => 'https://seolhopro.mycafe24.com/bizcard', // 예: https://yourdomain.com/bizcard

    // OCR 공급자: google_vision 또는 mock
    'ocr_provider' => 'google_vision',

    // Google Cloud Vision API Key
    // REST 예시: POST https://vision.googleapis.com/v1/images:annotate?key=YOUR_API_KEY
    'google_vision_api_key' => 'AIzaSyDykJSEh-2bL0IHMmDYH1K0QMeKqO88NQU',

    // 업로드 설정
    'upload_dir' => __DIR__ . '/storage/uploads',
    'max_upload_mb' => 8,

    // 업로드 이미지 보관 여부
    'keep_uploaded_files' => true,
];
