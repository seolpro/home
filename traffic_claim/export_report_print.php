<?php
require_once __DIR__ . '/functions.php';
require_login();
$caseId = (int)getv('case_id',0); $case = ensure_case_exists($caseId); $pdo = get_pdo();
$expenses = $pdo->prepare('SELECT * FROM expenses WHERE case_id=? ORDER BY expense_date,id'); $expenses->execute([$caseId]); $expenses = $expenses->fetchAll();
$docs = $pdo->prepare('SELECT * FROM case_documents WHERE case_id=? ORDER BY document_date,id'); $docs->execute([$caseId]); $docs = $docs->fetchAll();
$negs = $pdo->prepare('SELECT * FROM negotiations WHERE case_id=? ORDER BY event_date,id'); $negs->execute([$caseId]); $negs = $negs->fetchAll();
?><!doctype html>
<html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($case['title']) ?> 보고서</title>
<style>
body{font-family:'Malgun Gothic','Apple SD Gothic Neo',sans-serif;color:#111;margin:24px}h1,h2{margin:0 0 10px}h1{font-size:26px}h2{font-size:18px;margin-top:28px;border-bottom:2px solid #111;padding-bottom:6px}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #bbb;padding:8px;font-size:13px;vertical-align:top}th{background:#f5f5f5;text-align:left}.meta td,.meta th{font-size:14px}.muted{color:#666}.pre{white-space:pre-line}.actions{margin-bottom:16px}@media print {.actions{display:none} body{margin:12mm}}
</style></head><body>
<div class="actions"><button onclick="window.print()">인쇄 / PDF 저장</button> <a href="case_view.php?id=<?= $caseId ?>">돌아가기</a></div>
<h1>교통사고 합의자료 보고서</h1>
<div class="muted">출력시각: <?= e(date('Y-m-d H:i')) ?></div>
<h2>1. 사건 기본정보</h2>
<table class="meta">
<tr><th width="18%">사건명</th><td width="32%"><?= e($case['title']) ?></td><th width="18%">사고일</th><td><?= e($case['accident_date']) ?></td></tr>
<tr><th>사고장소</th><td><?= e($case['accident_place']) ?></td><th>상태</th><td><?= e(case_status_options()[$case['status']] ?? $case['status']) ?></td></tr>
<tr><th>상대 보험사</th><td><?= e($case['opponent_insurer']) ?></td><th>접수번호</th><td><?= e($case['claim_no']) ?></td></tr>
<tr><th>담당자</th><td><?= e($case['claim_handler']) ?> / <?= e($case['handler_contact']) ?></td><th>예상 합의금</th><td><?= $case['expected_settlement'] ? e(format_money($case['expected_settlement'])) : '-' ?></td></tr>
<tr><th>상해요약</th><td colspan="3"><?= e($case['injury_summary']) ?></td></tr>
<tr><th>메모</th><td colspan="3" class="pre"><?= e($case['notes']) ?></td></tr>
</table>
<h2>2. 지출내역</h2>
<table><thead><tr><th>일자</th><th>분류</th><th>내용</th><th>금액</th><th>메모</th></tr></thead><tbody>
<?php if(!$expenses): ?><tr><td colspan="5">등록 내역 없음</td></tr><?php endif; ?>
<?php foreach($expenses as $r): ?><tr><td><?= e($r['expense_date']) ?></td><td><?= e(expense_type_options()[$r['expense_type']] ?? $r['expense_type']) ?></td><td><?= e($r['description']) ?></td><td><?= e(format_money($r['amount'])) ?></td><td class="pre"><?= e($r['memo']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2>3. 보험사 제안금 / 합의이력</h2>
<table><thead><tr><th>일자</th><th>구분</th><th>채널/상대</th><th>요약</th><th>금액</th><th>상세</th></tr></thead><tbody>
<?php if(!$negs): ?><tr><td colspan="6">등록 내역 없음</td></tr><?php endif; ?>
<?php foreach($negs as $r): ?><tr><td><?= e($r['event_date']) ?></td><td><?= e(offer_stage_options()[$r['stage']] ?? $r['stage']) ?></td><td><?= e($r['channel']) ?> / <?= e($r['counterparty']) ?></td><td><?= e($r['summary']) ?></td><td><?= $r['amount'] !== null && $r['amount'] !== '' ? e(format_money($r['amount'])) : '-' ?></td><td class="pre"><?= e($r['details']) ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2>4. 보관 문서목록</h2>
<table><thead><tr><th>일자</th><th>분류</th><th>제목</th><th>파일명</th><th>메모</th></tr></thead><tbody>
<?php if(!$docs): ?><tr><td colspan="5">등록 내역 없음</td></tr><?php endif; ?>
<?php foreach($docs as $r): ?><tr><td><?= e($r['document_date']) ?></td><td><?= e(document_type_options()[$r['document_type']] ?? $r['document_type']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['original_name']) ?></td><td class="pre"><?= e($r['memo']) ?></td></tr><?php endforeach; ?>
</tbody></table>
</body></html>
