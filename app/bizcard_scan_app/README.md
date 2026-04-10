# PHP 서버형 명함 OCR 연락처 저장기

명함 이미지를 업로드하면 서버에서 OCR을 수행하고, 결과를 연락처 필드로 분리한 뒤 VCF 파일로 저장할 수 있는 PHP 앱입니다.

## 파일 구성

- `index.php` : 메인 UI
- `api/scan.php` : 이미지 업로드 + OCR 실행 + 파싱 JSON 반환
- `api/download_vcf.php` : 연락처를 `.vcf` 파일로 다운로드
- `lib/functions.php` : 공통 함수
- `config.example.php` : 설정 예시
- `storage/uploads/` : 업로드 저장 폴더

## 설치 방법

1. `config.example.php`를 `config.php`로 복사
2. `google_vision_api_key`에 키 입력
3. `ocr_provider`를 `google_vision`으로 유지
4. 전체 폴더를 PHP 서버에 업로드
5. `index.php` 접속

## 설정 예시

```php
<?php
return [
    'app_name' => 'BizCard Contact Saver',
    'base_url' => 'https://yourdomain.com/bizcard',
    'ocr_provider' => 'google_vision',
    'google_vision_api_key' => 'YOUR_API_KEY',
    'upload_dir' => __DIR__ . '/storage/uploads',
    'max_upload_mb' => 8,
    'keep_uploaded_files' => true,
];
```

## 테스트 모드

Google Vision API Key가 아직 없으면:

- `ocr_provider`를 `mock`으로 바꾸면 가짜 OCR 결과로 UI/VCF 흐름을 먼저 테스트할 수 있습니다.

## 주의

- 업로드 폴더에 쓰기 권한이 있어야 합니다.
- 실제 운영 시 업로드 이미지 자동 삭제 정책을 두는 것이 좋습니다.
- 파싱 규칙은 명함 레이아웃마다 100% 완벽할 수 없으므로 최종 저장 전 검수가 필요합니다.
