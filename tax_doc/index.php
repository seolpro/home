<?php
// /www/vat_docs/index.php
require_once __DIR__ . '/db.php';

ensureUploadDirs();

$msg   = '';
$error = '';

// 🔸 1) 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $id = (int)($_POST['delete_id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('잘못된 삭제 요청입니다.');
        }

        $pdo = getDB();
        $pdo->beginTransaction();

        // 삭제할 행 조회 (파일명 확보)
        $stmt = $pdo->prepare("SELECT tax_file, card_file FROM vat_docs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('이미 삭제되었거나 존재하지 않는 데이터입니다.');
        }

        // DB 행 삭제
        $del = $pdo->prepare("DELETE FROM vat_docs WHERE id = :id");
        $del->execute([':id' => $id]);

        $pdo->commit();

        // 실제 파일 삭제 (DB 삭제 후)
        if (!empty($row['tax_file'])) {
            $path = TAX_UPLOAD_DIR . '/' . $row['tax_file'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        if (!empty($row['card_file'])) {
            $path = CARD_UPLOAD_DIR . '/' . $row['card_file'];
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $msg = '✅ 증빙 1건이 삭제되었습니다.';
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = '❌ 삭제 중 오류가 발생했습니다: ' . $e->getMessage();
    }
}

// 🔸 2) 업로드/저장 처리 (삭제 요청이 아닐 때만)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $use_date    = isset($_POST['use_date']) ? trim($_POST['use_date']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $note        = isset($_POST['note']) ? trim($_POST['note']) : '';

        if ($use_date === '' || $description === '') {
            throw new RuntimeException('📌 사용날짜와 적요는 필수 입력입니다.');
        }

        $taxFileName  = null;
        $cardFileName = null;

        // 🔹 세금계산서 파일 업로드
        if (!empty($_FILES['tax_file']['name'])) {
            if ($_FILES['tax_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('세금계산서 업로드 중 오류가 발생했습니다.');
            }
            $f = $_FILES['tax_file'];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($f['tmp_name']);
            if (!isAllowedMime($mime)) {
                throw new RuntimeException('세금계산서 파일은 이미지 또는 PDF만 업로드 가능합니다.');
            }

            if ($f['size'] > 10 * 1024 * 1024) {
                throw new RuntimeException('세금계산서 파일 용량이 너무 큽니다. (최대 10MB)');
            }

            $safeName = makeSafeFilename($f['name']);
            $destPath = TAX_UPLOAD_DIR . '/' . $safeName;

            if (!move_uploaded_file($f['tmp_name'], $destPath)) {
                throw new RuntimeException('세금계산서 파일을 저장하지 못했습니다.');
            }

            $taxFileName = $safeName;
        }

        // 🔹 카드영수증 파일 업로드
        if (!empty($_FILES['card_file']['name'])) {
            if ($_FILES['card_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('카드영수증 업로드 중 오류가 발생했습니다.');
            }
            $f = $_FILES['card_file'];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($f['tmp_name']);
            if (!isAllowedMime($mime)) {
                throw new RuntimeException('카드영수증 파일은 이미지 또는 PDF만 업로드 가능합니다.');
            }

            if ($f['size'] > 10 * 1024 * 1024) {
                throw new RuntimeException('카드영수증 파일 용량이 너무 큽니다. (최대 10MB)');
            }

            $safeName = makeSafeFilename($f['name']);
            $destPath = CARD_UPLOAD_DIR . '/' . $safeName;

            if (!move_uploaded_file($f['tmp_name'], $destPath)) {
                throw new RuntimeException('카드영수증 파일을 저장하지 못했습니다.');
            }

            $cardFileName = $safeName;
        }

        // DB 저장
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO vat_docs (use_date, description, tax_file, card_file, note)
            VALUES (:use_date, :description, :tax_file, :card_file, :note)
        ");
        $stmt->execute([
            ':use_date'    => $use_date,
            ':description' => $description,
            ':tax_file'    => $taxFileName,
            ':card_file'   => $cardFileName,
            ':note'        => $note,
        ]);

        $msg = '✅ 증빙이 정상적으로 저장되었습니다.';
    } catch (Throwable $e) {
        $error = '❌ ' . $e->getMessage();
    }
}

// 🔸 목록 조회 (최근 등록순)
$pdo = getDB();
$listStmt = $pdo->query("
    SELECT * FROM vat_docs
    ORDER BY use_date DESC, id DESC
");
$rows = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>부가세 증빙 보관함</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .page-title {
            font-weight: 700;
        }
        .card {
            border-radius: 1rem;
        }
        .table td, .table th {
            vertical-align: middle;
            font-size: 0.9rem;
        }
        .file-thumb {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container py-4">

    <h3 class="mb-1 text-center page-title">📁 부가세 신고용 증빙 보관함</h3>
    <p class="text-center text-muted mb-4">
        세금계산서 / 카드(사업자지출증빙) 영수증을 업로드하여 보관하고,<br>
        필요 시 이미지를 열어서 복사하거나 파일로 내려받을 수 있습니다.
    </p>

    <?php if ($msg): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- 입력 카드 -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">📝 증빙 등록</h5>
            <form method="post" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">사용날짜 <span class="text-danger">*</span></label>
                        <!-- ✅ 오늘 날짜를 기본값으로 -->
                        <input
                            type="date"
                            name="use_date"
                            class="form-control"
                            required
                            value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">적요 <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" placeholder="예: ○○식당 접대비, ○○업체 세금계산서" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">비고</label>
                        <input type="text" name="note" class="form-control" placeholder="필요 시 메모">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">세금계산서 업로드</label>
                        <input type="file" name="tax_file" class="form-control"
                               accept="image/*,application/pdf">
                        <div class="form-text">이미지 또는 PDF (최대 10MB)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">카드(사업자 지출증빙) 영수증 업로드</label>
                        <input type="file" name="card_file" class="form-control"
                               accept="image/*,application/pdf">
                        <div class="form-text">이미지 또는 PDF (최대 10MB)</div>
                    </div>
                </div>

                <div class="mt-3 text-end">
                    <button type="submit" class="btn btn-primary">
                        💾 저장하기
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 목록 카드 -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">📚 등록된 증빙 목록</h5>

            <?php if (empty($rows)): ?>
                <p class="text-muted mb-0">아직 등록된 증빙이 없습니다.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 110px;">사용날짜</th>
                            <th style="width: 220px;">적요</th>
                            <th>세금계산서</th>
                            <th>카드영수증</th>
                            <th style="width: 160px;">비고</th>
                            <th style="width: 120px;">등록일시</th>
                            <!-- ✅ 삭제 버튼 컬럼 -->
                            <th style="width: 80px;">관리</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['use_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($r['description'], ENT_QUOTES, 'UTF-8') ?></td>

                                <!-- 세금계산서 -->
                                <td>
                                    <?php if (!empty($r['tax_file'])): ?>
                                        <?php
                                            $taxUrl = 'uploads/tax/' . rawurlencode($r['tax_file']);
                                            $ext = strtolower(pathinfo($r['tax_file'], PATHINFO_EXTENSION));
                                        ?>
                                        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                            <a href="<?= $taxUrl ?>" target="_blank" title="새 창에서 크게 보기 / 저장">
                                                <img src="<?= $taxUrl ?>" class="file-thumb" alt="세금계산서">
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= $taxUrl ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                📄 세금계산서 열기
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 카드영수증 -->
                                <td>
                                    <?php if (!empty($r['card_file'])): ?>
                                        <?php
                                            $cardUrl = 'uploads/card/' . rawurlencode($r['card_file']);
                                            $ext2 = strtolower(pathinfo($r['card_file'], PATHINFO_EXTENSION));
                                        ?>
                                        <?php if (in_array($ext2, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                            <a href="<?= $cardUrl ?>" target="_blank" title="새 창에서 크게 보기 / 저장">
                                                <img src="<?= $cardUrl ?>" class="file-thumb" alt="카드영수증">
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= $cardUrl ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                📄 카드영수증 열기
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= htmlspecialchars($r['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($r['created_at'], ENT_QUOTES, 'UTF-8') ?></td>

                                <!-- ✅ 삭제 버튼 -->
                                <td>
                                    <form method="post" class="d-inline"
                                          onsubmit="return confirm('이 증빙을 정말 삭제하시겠습니까?');">
                                        <input type="hidden" name="delete_id"
                                               value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            삭제
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2 mb-0">
                    ※ 이미지는 클릭 후 새 창에서 열리므로, <b>마우스 우클릭 → 이미지 복사 / 이미지로 저장</b> 하시면 됩니다.<br>
                    ※ PDF는 브라우저에서 열리며, 브라우저의 다운로드 버튼으로 저장 가능합니다.
                </p>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>
