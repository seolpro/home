<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>명함 스캔</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #0f172a;
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans KR", sans-serif;
        }
        .wrap {
            max-width: 780px;
            margin: 0 auto;
            padding: 16px;
        }
        .card {
            background: #1e293b;
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
        }
        .title {
            font-size: 24px;
            font-weight: 800;
            margin: 0 0 14px;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .field {
            margin-bottom: 12px;
        }
        .field.full {
            grid-column: 1 / -1;
        }
        label {
            display: block;
            font-size: 13px;
            margin-bottom: 6px;
            color: #cbd5e1;
            font-weight: 600;
        }
        input, textarea {
            width: 100%;
            border: 1px solid #334155;
            background: #0f172a;
            color: #f8fafc;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 15px;
            outline: none;
        }
        textarea {
            min-height: 110px;
            resize: vertical;
        }
        .btns {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .btn {
            border: 0;
            border-radius: 14px;
            padding: 14px 18px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
        }
        .btn-primary { background: #22c55e; color: #052e16; }
        .btn-dark { background: #334155; color: #fff; }
        .btn-yellow { background: #f59e0b; color: #1c1917; }
        .preview {
            width: 100%;
            min-height: 220px;
            background: #0b1220;
            border: 1px solid #334155;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 16px;
        }
        .preview img {
            width: 100%;
            height: auto;
            display: block;
        }
        .status {
            margin-bottom: 14px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 12px 14px;
            color: #cbd5e1;
            font-size: 14px;
        }
        .ocrbox {
            margin-top: 12px;
            background: #0b1220;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 12px;
            white-space: pre-wrap;
            word-break: break-word;
            min-height: 120px;
            color: #e2e8f0;
            font-size: 14px;
        }
        .hidden { display: none; }
        @media (max-width: 640px) {
            .row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1 class="title">📰명함 스캔</h1>

        <div class="btns">
            <label class="btn btn-primary" for="imageInput">📷 명함 촬영 / 선택</label>
            <button type="button" class="btn btn-dark" id="scanBtn" disabled>🔍 분석(입력)</button>
            <button type="button" class="btn btn-yellow" id="resetBtn">🔂초기화</button>
        </div>

        <input type="file" id="imageInput" accept="image/*,.jpg,.jpeg,.png,.webp,.heic,.heif" capture="environment" class="hidden">

        <div class="preview" id="previewBox">이미지를 선택하세요.</div>
        <div class="status" id="statusBox">대기 중</div>

        <div class="row">
            <div class="field">
                <label for="name">이름</label>
                <input type="text" id="name">
            </div>
            <div class="field">
                <label for="job_title">직책</label>
                <input type="text" id="job_title">
            </div>
            <div class="field">
                <label for="company">회사명</label>
                <input type="text" id="company">
            </div>
            <div class="field">
                <label for="department">부서</label>
                <input type="text" id="department">
            </div>
            <div class="field">
                <label for="mobile">휴대폰</label>
                <input type="text" id="mobile">
            </div>
            <div class="field">
                <label for="phone">전화</label>
                <input type="text" id="phone">
            </div>
            <div class="field">
                <label for="email">이메일</label>
                <input type="text" id="email">
            </div>
            <div class="field">
                <label for="website">웹사이트</label>
                <input type="text" id="website">
            </div>
            <div class="field full">
                <label for="address">주소</label>
                <input type="text" id="address">
            </div>
            <div class="field full">
                <label for="memo">메모</label>
                <textarea id="memo"></textarea>
            </div>
        </div>

        <div class="btns">
            <button type="button" class="btn btn-primary" onclick="addContactOnAndroid()">📲 연락처 추가</button>
        </div>

        <div class="ocrbox" id="ocrTextBox"></div>

        <form id="scanForm" class="hidden">
            <input type="file" name="image" id="scanImageMirror">
        </form>

        <form id="vcfDownloadForm" action="api/download_vcf.php" method="post" target="_blank" class="hidden">
            <input type="hidden" name="name" id="vcf_name">
            <input type="hidden" name="company" id="vcf_company">
            <input type="hidden" name="job_title" id="vcf_job_title">
            <input type="hidden" name="department" id="vcf_department">
            <input type="hidden" name="mobile" id="vcf_mobile">
            <input type="hidden" name="phone" id="vcf_phone">
            <input type="hidden" name="email" id="vcf_email">
            <input type="hidden" name="website" id="vcf_website">
            <input type="hidden" name="address" id="vcf_address">
            <input type="hidden" name="memo" id="vcf_memo">
        </form>
    </div>
</div>

<script>
const imageInput = document.getElementById('imageInput');
const previewBox = document.getElementById('previewBox');
const scanBtn = document.getElementById('scanBtn');
const resetBtn = document.getElementById('resetBtn');
const statusBox = document.getElementById('statusBox');
const ocrTextBox = document.getElementById('ocrTextBox');

let previewUrl = null;

imageInput.addEventListener('change', function () {
    const file = this.files && this.files[0] ? this.files[0] : null;

    if (!file) {
        statusBox.textContent = '이미지를 다시 선택해 주세요.';
        scanBtn.disabled = true;
        return;
    }

    scanBtn.disabled = false;

    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
    }

    previewUrl = URL.createObjectURL(file);
    previewBox.innerHTML = '<img src="' + previewUrl + '" alt="명함 이미지">';
    statusBox.textContent = '이미지 선택 완료: ' + (file.name || 'camera.jpg') + ' / ' + Math.round(file.size / 1024) + 'KB';
});

scanBtn.addEventListener('click', async function () {
    const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;

    if (!file) {
        alert('명함 이미지를 먼저 촬영하거나 선택해 주세요.');
        statusBox.textContent = '명함 이미지를 업로드해 주세요.';
        return;
    }

    const fd = new FormData();
    fd.append('image', file, file.name || 'camera.jpg');

    statusBox.textContent = '분석 중...';
    scanBtn.disabled = true;

    try {
        const res = await fetch('api/scan.php', {
            method: 'POST',
            body: fd
        });

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || '분석 실패');
        }

        document.getElementById('name').value = data.contact.name || '';
        document.getElementById('job_title').value = data.contact.job_title || '';
        document.getElementById('company').value = data.contact.company || '';
        document.getElementById('department').value = data.contact.department || '';
        document.getElementById('mobile').value = data.contact.mobile || '';
        document.getElementById('phone').value = data.contact.phone || '';
        document.getElementById('email').value = data.contact.email || '';
        document.getElementById('website').value = data.contact.website || '';
        document.getElementById('address').value = data.contact.address || '';
        document.getElementById('memo').value = data.contact.memo || '';
        ocrTextBox.textContent = data.ocr_text || '';

        statusBox.textContent = '분석 완료';
    } catch (e) {
        statusBox.textContent = '오류: ' + e.message;
    } finally {
        scanBtn.disabled = false;
    }
});

resetBtn.addEventListener('click', function () {
    imageInput.value = '';
    scanBtn.disabled = true;
    statusBox.textContent = '대기 중';
    ocrTextBox.textContent = '';
    previewBox.innerHTML = '이미지를 선택하세요.';

    ['name','job_title','company','department','mobile','phone','email','website','address','memo'].forEach(id => {
        document.getElementById(id).value = '';
    });

    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    }
});

function submitVcfDownload() {
    document.getElementById('vcf_name').value = document.getElementById('name').value || '';
    document.getElementById('vcf_company').value = document.getElementById('company').value || '';
    document.getElementById('vcf_job_title').value = document.getElementById('job_title').value || '';
    document.getElementById('vcf_department').value = document.getElementById('department').value || '';
    document.getElementById('vcf_mobile').value = document.getElementById('mobile').value || '';
    document.getElementById('vcf_phone').value = document.getElementById('phone').value || '';
    document.getElementById('vcf_email').value = document.getElementById('email').value || '';
    document.getElementById('vcf_website').value = document.getElementById('website').value || '';
    document.getElementById('vcf_address').value = document.getElementById('address').value || '';
    document.getElementById('vcf_memo').value = document.getElementById('memo').value || '';
    document.getElementById('vcfDownloadForm').submit();
}

function encIntentValue(v) {
    return encodeURIComponent((v || '').trim());
}

function isAndroid() {
    return /Android/i.test(navigator.userAgent || '');
}

function isKakaoInApp() {
    return /KAKAOTALK/i.test(navigator.userAgent || '');
}

function addContactOnAndroid() {
    const name = document.getElementById('name').value || '';
    const company = document.getElementById('company').value || '';
    const jobTitle = document.getElementById('job_title').value || '';
    const department = document.getElementById('department').value || '';
    const mobile = document.getElementById('mobile').value || '';
    const phone = document.getElementById('phone').value || '';
    const email = document.getElementById('email').value || '';
    const website = document.getElementById('website').value || '';
    const address = document.getElementById('address').value || '';
    const memo = document.getElementById('memo').value || '';

    if (!isAndroid()) {
        alert('이 기능은 안드로이드에서 연락처 추가 화면을 여는 방식입니다.');
        return;
    }

    if (!name && !mobile && !phone && !email) {
        alert('이름, 전화번호, 이메일 중 하나 이상은 있어야 합니다.');
        return;
    }

    const notes = [department, website, memo].filter(Boolean).join('\n');

    // Android 연락처 추가 화면 열기
    // ACTION_INSERT + 연락처 MIME 타입 + extras
    let intentUrl =
        'intent:#Intent;' +
        'action=android.intent.action.INSERT;' +
        'type=vnd.android.cursor.dir/contact;';

    if (name) intentUrl += 'S.name=' + encIntentValue(name) + ';';
    if (mobile) intentUrl += 'S.phone=' + encIntentValue(mobile) + ';';
    else if (phone) intentUrl += 'S.phone=' + encIntentValue(phone) + ';';

    if (phone && mobile && phone !== mobile) {
        intentUrl += 'S.secondary_phone=' + encIntentValue(phone) + ';';
    }

    if (email) intentUrl += 'S.email=' + encIntentValue(email) + ';';
    if (company) intentUrl += 'S.company=' + encIntentValue(company) + ';';
    if (jobTitle) intentUrl += 'S.job_title=' + encIntentValue(jobTitle) + ';';
    if (address) intentUrl += 'S.postal=' + encIntentValue(address) + ';';
    if (notes) intentUrl += 'S.notes=' + encIntentValue(notes) + ';';

    // 인텐트 실행 실패 시 돌아올 fallback
    intentUrl += 'S.browser_fallback_url=' + encodeURIComponent(window.location.href) + ';';
    intentUrl += 'end';

    if (isKakaoInApp()) {
        alert('🧩입력된 내용 확인 후 저장해 주세요');
    }

    window.location.href = intentUrl;
    
}
</script>
<script>
function isAndroid() {
    return /Android/i.test(navigator.userAgent || '');
}

function isChromeLike() {
    const ua = navigator.userAgent || '';
    return /Chrome|CriOS|EdgA|SamsungBrowser/i.test(ua);
}

function isKakaoInApp() {
    return /KAKAOTALK/i.test(navigator.userAgent || '');
}

function fillVcfForm() {
    document.getElementById('vcf_name').value = document.getElementById('name').value || '';
    document.getElementById('vcf_company').value = document.getElementById('company').value || '';
    document.getElementById('vcf_job_title').value = document.getElementById('job_title').value || '';
    document.getElementById('vcf_department').value = document.getElementById('department').value || '';
    document.getElementById('vcf_mobile').value = document.getElementById('mobile').value || '';
    document.getElementById('vcf_phone').value = document.getElementById('phone').value || '';
    document.getElementById('vcf_email').value = document.getElementById('email').value || '';
    document.getElementById('vcf_website').value = document.getElementById('website').value || '';
    document.getElementById('vcf_address').value = document.getElementById('address').value || '';
    document.getElementById('vcf_memo').value = document.getElementById('memo').value || '';
}

function smartAddContact() {
    const name = document.getElementById('name').value.trim();
    const company = document.getElementById('company').value.trim();
    const jobTitle = document.getElementById('job_title').value.trim();
    const department = document.getElementById('department').value.trim();
    const mobile = document.getElementById('mobile').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const email = document.getElementById('email').value.trim();
    const website = document.getElementById('website').value.trim();
    const address = document.getElementById('address').value.trim();
    const memo = document.getElementById('memo').value.trim();

    if (!name && !mobile && !phone && !email) {
        alert('이름, 전화번호, 이메일 중 하나 이상이 필요합니다.');
        return;
    }

    const notes = [department, website, memo].filter(Boolean).join('\n');

    // Android + Chrome 계열이면 intent 우선
    if (isAndroid() && isChromeLike() && !isKakaoInApp()) {
        const enc = encodeURIComponent;
        let intentUrl =
            'intent:#Intent;' +
            'action=android.intent.action.INSERT;' +
            'type=vnd.android.cursor.dir/contact;';

        if (name) intentUrl += 'S.name=' + enc(name) + ';';
        if (mobile) intentUrl += 'S.phone=' + enc(mobile) + ';';
        else if (phone) intentUrl += 'S.phone=' + enc(phone) + ';';

        if (phone && mobile && phone !== mobile) {
            intentUrl += 'S.secondary_phone=' + enc(phone) + ';';
        }

        if (email) intentUrl += 'S.email=' + enc(email) + ';';
        if (company) intentUrl += 'S.company=' + enc(company) + ';';
        if (jobTitle) intentUrl += 'S.job_title=' + enc(jobTitle) + ';';
        if (address) intentUrl += 'S.postal=' + enc(address) + ';';
        if (notes) intentUrl += 'S.notes=' + enc(notes) + ';';
        intentUrl += 'S.browser_fallback_url=' + enc(window.location.href) + ';';
        intentUrl += 'end';

        const start = Date.now();
        window.location.href = intentUrl;

        // 실패 시 VCF fallback
        setTimeout(() => {
            if (Date.now() - start < 1800) {
                fillVcfForm();
                document.getElementById('vcfDownloadForm').submit();
            }
        }, 1200);
        return;
    }

    // 그 외 브라우저는 VCF fallback
    fillVcfForm();
    document.getElementById('vcfDownloadForm').submit();
}
</script>
</body>
</html>