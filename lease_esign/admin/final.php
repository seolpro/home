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
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/app.css">
    <title>최종 PDF</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
<main class="wrap">
    <div class="card no-print">
        <h1>최종 PDF 생성 및 발송</h1>
        <p>
            먼저 계약서를 PDF로 생성·저장한 후 임대인과 임차인에게
            각각 안전한 PDF 다운로드 링크를 문자로 발송할 수 있습니다.
        </p>

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

<button class="btn blue" id="downloadPdf" type="button" disabled>
    📥 PDF 다운로드
</button>

            <a class="btn gray" href="view.php?id=<?=$id?>">⬅️돌아가기</a>
        </div>

        <div id="msg" class="alert" style="display:none"></div>
    </div>

    <?=render_contract($c)?>
</main>

<script>
const id = <?=json_encode($id, JSON_UNESCAPED_UNICODE)?>;
const msg = document.getElementById('msg');
const makeBtn = document.getElementById('make');
const sendLessorBtn = document.getElementById('sendLessor');
const sendLesseeBtn = document.getElementById('sendLessee');
const downloadPdfBtn = document.getElementById('downloadPdf');

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
        const opt = {
            margin: 8,
            filename: 'lease_' + id + '.pdf',
            image: { type: 'jpeg', quality: 0.96 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'legacy'] }
        };

        const blob = await html2pdf()
            .set(opt)
            .from(document.getElementById('contractDocument'))
            .outputPdf('blob');

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
        showMessage(false, 'PDF 생성 또는 저장 중 오류가 발생했습니다.');
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
