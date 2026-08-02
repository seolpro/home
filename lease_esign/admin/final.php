<?php
require_once dirname(__DIR__) . '/lib.php';
admin_required();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM contracts WHERE id = ?');
$stmt->execute([$id]);
$c = $stmt->fetch();

if (!$c || $c['status'] !== 'completed') {
    die('최종 확정된 계약서가 아닙니다.');
}

$hasIdCard = !empty($c['lessee_id_image']);
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/app.css">
    <title>최종 PDF</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .id-upload-box {
            margin: 18px 0;
            padding: 18px;
            border: 2px dashed #aab4c3;
            border-radius: 12px;
            background: #f8fafc;
        }
        .id-upload-box h2 { margin-top: 0; font-size: 20px; }
        .id-preview {
            display: <?= $hasIdCard ? 'block' : 'none' ?>;
            margin-top: 14px;
            padding: 12px;
            background: #fff;
            border: 1px solid #d7dde5;
            border-radius: 10px;
            text-align: center;
        }
        .id-preview img {
            max-width: 100%;
            max-height: 320px;
            object-fit: contain;
        }
        /* PDF 출력용 A4 별첨 페이지 */
        .appendix-page {
            page-break-before: always;
            break-before: page;
            width: 210mm;
            height: 297mm;
            min-height: 297mm;
            max-height: 297mm;
            overflow: hidden;
            box-sizing: border-box;
            padding: 16mm 14mm 12mm;
            margin: 0;
            background: #fff;
            text-align: center;
        }
        .appendix-page h1 {
            margin: 0 0 10mm;
            padding: 0;
            border: 0;
            font-size: 23px;
            line-height: 1.3;
        }
        .appendix-image-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 218mm;
            min-height: 0;
            overflow: hidden;
        }
        .appendix-image-wrap img {
            display: block;
            width: auto;
            height: auto;
            max-width: 178mm;
            max-height: 210mm;
            object-fit: contain;
        }
        .appendix-note {
            margin: 7mm 0 0;
            font-size: 10.5px;
            line-height: 1.55;
            color: #555;
        }

        /* 실제 PDF 생성 시 화면 위치의 영향을 받지 않는 독립 출력영역 */
        .pdf-export-root {
            position: absolute;
            left: 0;
            top: 0;
            width: 794px;
            margin: 0;
            padding: 0;
            background: #fff;
            z-index: -2147483647;
            pointer-events: none;
            overflow: visible;
        }
        .pdf-export-root #pdfDocument {
            width: 794px !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
            transform: none !important;
        }
        .pdf-export-root .contract {
            width: 794px !important;
            max-width: 794px !important;
            min-width: 794px !important;
            margin: 0 !important;
            padding: 38px !important;
            border: 0 !important;
            box-shadow: none !important;
            box-sizing: border-box !important;
            background: #fff !important;
            transform: none !important;
            position: relative !important;
            left: 0 !important;
            right: auto !important;
        }
        .pdf-export-root .appendix-page {
            width: 794px !important;
            min-width: 794px !important;
            max-width: 794px !important;
            height: 1123px !important;
            min-height: 1123px !important;
            max-height: 1123px !important;
            margin: 0 !important;
            padding: 60px 53px 45px !important;
            box-sizing: border-box !important;
            transform: none !important;
            position: relative !important;
            left: 0 !important;
        }
        .pdf-export-root .appendix-image-wrap {
            height: 824px !important;
        }
        .pdf-export-root .appendix-image-wrap img {
            max-width: 672px !important;
            max-height: 794px !important;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<main class="wrap">
    <div class="card no-print">
        <h1>최종 PDF 생성 및 발송</h1>
        <p>
            임차인 신분증을 첨부할 경우 먼저 이미지를 업로드한 후 PDF를 생성하세요.
            업로드된 신분증은 최종 PDF의 마지막 페이지에 별첨으로 들어갑니다.
        </p>

        <div class="id-upload-box">
            <h2>🪪 임차인 신분증 첨부</h2>
            <p class="small">
                JPG, JPEG, PNG 또는 WEBP 이미지만 가능하며 최대 8MB입니다.
                주민등록번호 뒷자리 등 불필요한 정보는 업로드 전에 마스킹하는 것을 권장합니다.
            </p>

            <input
                type="file"
                id="idCardFile"
                accept="image/jpeg,image/png,image/webp"
            >
            <button class="btn" id="uploadIdCard" type="button">
                📤 신분증 업로드·교체
            </button>

            <div class="id-preview" id="idPreview">
                <strong>현재 첨부된 신분증</strong><br><br>
                <img
                    id="idPreviewImage"
                    src="<?= $hasIdCard ? 'id_image.php?id=' . $id . '&v=' . time() : '' ?>"
                    alt="임차인 신분증 미리보기"
                >
            </div>
        </div>

        <div class="actions">
            <button class="btn big green" id="make" type="button">
                📝 PDF 생성·저장
            </button>

            <button class="btn" id="sendLessor" type="button" disabled>
                📨 임대인에게 PDF 전송
            </button>

            <button class="btn" id="sendLessee" type="button" disabled>
                📨 임차인에게 PDF 전송
            </button>

            <a
                class="btn"
                id="downloadPdf"
                href="../download.php?id=<?=$id?>"
                <?=empty($c['final_pdf']) ? 'style="display:none"' : ''?>
            >
                📥 생성된 PDF 다운로드
            </a>

            <a class="btn gray" href="view.php?id=<?=$id?>">⬅️돌아가기</a>
        </div>

        <div id="msg" class="alert" style="display:none"></div>
    </div>

    <div id="pdfDocument">
        <?=render_contract($c)?>

        <section
            class="appendix-page"
            id="idAppendix"
            style="<?= $hasIdCard ? '' : 'display:none' ?>"
        >
            <h1>별첨 1. 임차인 신분증 사본</h1>
            <div class="appendix-image-wrap">
                <img
                    id="appendixIdImage"
                    src="<?= $hasIdCard ? 'id_image.php?id=' . $id . '&v=' . time() : '' ?>"
                    alt="임차인 신분증 사본"
                >
            </div>
            <div class="appendix-note">
                본 신분증 사본은 본 임대차계약의 당사자 확인을 위한 별첨 자료입니다.<br>
                개인정보 보호를 위하여 계약 이행 및 분쟁 대응 목적 외의 사용을 금합니다.
            </div>
        </section>
    </div>
</main>

<script>
const id = <?=json_encode($id, JSON_UNESCAPED_UNICODE)?>;
const csrf = <?=json_encode(csrf(), JSON_UNESCAPED_UNICODE)?>;
const msg = document.getElementById('msg');
const makeBtn = document.getElementById('make');
const sendLessorBtn = document.getElementById('sendLessor');
const sendLesseeBtn = document.getElementById('sendLessee');
const downloadPdfBtn = document.getElementById('downloadPdf');
const uploadIdCardBtn = document.getElementById('uploadIdCard');
const idCardFile = document.getElementById('idCardFile');
const idPreview = document.getElementById('idPreview');
const idPreviewImage = document.getElementById('idPreviewImage');
const idAppendix = document.getElementById('idAppendix');
const appendixIdImage = document.getElementById('appendixIdImage');

const hasSavedPdf = <?=!empty($c['final_pdf']) ? 'true' : 'false'?>;
if (hasSavedPdf) {
    sendLessorBtn.disabled = false;
    sendLesseeBtn.disabled = false;
}

function showMessage(ok, text) {
    msg.style.display = 'block';
    msg.className = ok ? 'alert ok' : 'alert err';
    msg.textContent = text;
}

function waitForImage(img) {
    return new Promise((resolve, reject) => {
        if (!img || !img.src) return resolve();
        if (img.complete && img.naturalWidth > 0) return resolve();
        img.onload = () => resolve();
        img.onerror = () => reject(new Error('신분증 이미지를 불러오지 못했습니다.'));
    });
}

uploadIdCardBtn.addEventListener('click', async () => {
    const file = idCardFile.files[0];
    if (!file) {
        showMessage(false, '업로드할 신분증 이미지 파일을 선택해 주세요.');
        return;
    }

    uploadIdCardBtn.disabled = true;
    uploadIdCardBtn.textContent = '업로드 중...';

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('csrf', csrf);
        fd.append('id_card', file);

        const response = await fetch('../api/upload_id_card.php', {
            method: 'POST',
            body: fd
        });
        const result = await response.json();

        if (!result.ok) {
            throw new Error(result.message || '신분증 업로드에 실패했습니다.');
        }

        const imageUrl = 'id_image.php?id=' + encodeURIComponent(id) + '&v=' + Date.now();
        idPreviewImage.src = imageUrl;
        appendixIdImage.src = imageUrl;
        idPreview.style.display = 'block';
        idAppendix.style.display = 'block';

        // 신분증이 변경되면 기존 최종 PDF는 무효화되므로 다시 생성해야 함
        sendLessorBtn.disabled = true;
        sendLesseeBtn.disabled = true;
        downloadPdfBtn.style.display = 'none';

        showMessage(true, result.message || '신분증을 업로드했습니다. PDF를 다시 생성해 주세요.');
    } catch (error) {
        showMessage(false, error.message || '신분증 업로드 중 오류가 발생했습니다.');
    } finally {
        uploadIdCardBtn.disabled = false;
        uploadIdCardBtn.textContent = '📤 신분증 업로드·교체';
    }
});

async function sendPdfLink(party, button) {
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = '문자 발송 중...';

    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('party', party);

        const response = await fetch('../api/send_final.php', {
            method: 'POST',
            body: fd
        });

        const result = await response.json();
        showMessage(Boolean(result.ok), result.message || '처리 결과를 확인할 수 없습니다.');
    } catch (error) {
        showMessage(false, '문자 발송 중 통신 오류가 발생했습니다.');
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

makeBtn.addEventListener('click', async () => {
    makeBtn.disabled = true;
    msg.style.display = 'block';
    msg.className = 'alert warn';
    msg.textContent = 'PDF를 생성하고 있습니다.';

    try {
        if (idAppendix.style.display !== 'none') {
            await waitForImage(appendixIdImage);
        }

        /*
         * 화면의 관리자 카드 아래에 있는 원본 요소를 그대로 PDF로 만들면
         * html2pdf가 화면상의 Y좌표를 페이지 나눔 계산에 반영하여 첫 페이지에
         * 큰 공백이나 좌우 잘림이 생길 수 있습니다. 출력 전용 복제본을
         * A4 폭 794px, 좌표 0에서 생성해 렌더링 기준점을 고정합니다.
         */
        const source = document.getElementById('pdfDocument');
        const exportRoot = document.createElement('div');
        exportRoot.className = 'pdf-export-root';
        exportRoot.setAttribute('aria-hidden', 'true');
        exportRoot.appendChild(source.cloneNode(true));
        document.body.appendChild(exportRoot);

        try {
            const clonedImages = exportRoot.querySelectorAll('img');
            await Promise.all(Array.from(clonedImages).map(waitForImage));

            const opt = {
                margin: 0,
                filename: 'lease_' + id + '.pdf',
                image: { type: 'jpeg', quality: 0.97 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    allowTaint: false,
                    logging: false,
                    scrollX: 0,
                    scrollY: 0,
                    x: 0,
                    y: 0,
                    width: 794,
                    windowWidth: 794
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait',
                    compress: true
                },
                pagebreak: {
                    mode: ['css', 'legacy'],
                    before: '.appendix-page',
                    avoid: ['tr', '.signature-box']
                }
            };

            var blob = await html2pdf()
                .set(opt)
                .from(exportRoot.firstElementChild)
                .outputPdf('blob');
        } finally {
            exportRoot.remove();
        }

        const fd = new FormData();
        fd.append('id', id);
        fd.append('pdf', blob, 'lease_' + id + '.pdf');

        const response = await fetch('../api/upload_pdf.php', {
            method: 'POST',
            body: fd
        });

        const result = await response.json();
        showMessage(Boolean(result.ok), result.message || 'PDF 생성 결과를 확인할 수 없습니다.');

        if (result.ok) {
            sendLessorBtn.disabled = false;
            sendLesseeBtn.disabled = false;
            downloadPdfBtn.href = '../download.php?id=' + encodeURIComponent(id) + '&t=' + Date.now();
            downloadPdfBtn.style.display = 'inline-flex';
        }
    } catch (error) {
        showMessage(false, error.message || 'PDF 생성 또는 저장 중 오류가 발생했습니다.');
    } finally {
        makeBtn.disabled = false;
    }
});

sendLessorBtn.addEventListener('click', () => {
    sendPdfLink('lessor', sendLessorBtn);
});

sendLesseeBtn.addEventListener('click', () => {
    sendPdfLink('lessee', sendLesseeBtn);
});
</script>
</body>
</html>
