<?php
require_once __DIR__ . '/functions.php';
require_login();
$caseId = (int)getv('case_id',0); $case = ensure_case_exists($caseId); $pdo = get_pdo();
$expenses = $pdo->prepare('SELECT * FROM expenses WHERE case_id=? ORDER BY expense_date,id'); $expenses->execute([$caseId]); $expenses = $expenses->fetchAll();
$docs = $pdo->prepare('SELECT * FROM case_documents WHERE case_id=? ORDER BY document_date,id'); $docs->execute([$caseId]); $docs = $docs->fetchAll();
$negs = $pdo->prepare('SELECT * FROM negotiations WHERE case_id=? ORDER BY event_date,id'); $negs->execute([$caseId]); $negs = $negs->fetchAll();
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="traffic_claim_case_' . $caseId . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['구분','사건명','사고일','세부구분','일자','내용/제목','금액','비고']);
fputcsv($out, ['사건기본',$case['title'],$case['accident_date'],'상태','',$case['status'],$case['expected_settlement'],$case['notes']]);
foreach($expenses as $r) fputcsv($out, ['지출',$case['title'],$case['accident_date'],$r['expense_type'],$r['expense_date'],$r['description'],$r['amount'],$r['memo']]);
foreach($negs as $r) fputcsv($out, ['협의이력',$case['title'],$case['accident_date'],$r['stage'],$r['event_date'],$r['summary'],$r['amount'],$r['details']]);
foreach($docs as $r) fputcsv($out, ['문서',$case['title'],$case['accident_date'],$r['document_type'],$r['document_date'],$r['title'],'',$r['original_name']]);
fclose($out); exit;
