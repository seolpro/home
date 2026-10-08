<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config.php';
require_once dirname(__DIR__).'/lib/common.php';
tp_admin_required();
function bs_h(string $s): string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>공식 화물연결 경로 점검</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f5f6f8}.card{border:0;border-radius:16px}.ok{color:#198754}.wait{color:#dc3545}</style></head><body><div class="container py-4" style="max-width:1100px">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><div><h2 class="fw-bold mb-1">공식 화물연결 경로 점검</h2><div class="text-secondary">v1.8.5 · MRN → B/L/MSN/cargMtNo 자동연결 가능 여부를 추측 없이 관리</div></div><div><a class="btn btn-outline-dark" href="mrn_bridge.php">MRN 실험실</a> <a class="btn btn-outline-dark" href="cargo_link.php">화물연결 진단</a></div></div>
<div class="alert alert-info mt-4"><b>현재 확정:</b> API024에서 Shanghai 실제 전출항지와 11자리 MRN을 확보할 수 있고, API001은 완성된 cargMtNo(15~19자리) 또는 MBL/HBL+입항년도를 입력받습니다.</div>
<div class="card shadow-sm mb-3"><div class="card-body"><h5 class="fw-bold">연결 후보 서비스 상태</h5><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>서비스</th><th>확인된 역할</th><th>자동연결 판단</th></tr></thead><tbody>
<tr><td><b>API024</b><br>입출항보고내역조회</td><td>선박/입항/실제 전출항지/MRN(제출번호) 교차검증</td><td class="ok fw-bold">사용중 ✓</td></tr>
<tr><td><b>API001</b><br>화물통관 진행정보</td><td>cargMtNo 또는 MBL/HBL+년도 → 품명·선박·적재항·수량·중량 등</td><td class="ok fw-bold">사용중 ✓</td></tr>
<tr><td><b>API021</b><br>입항보고내역조회(해상)</td><td>공식 서비스 목록에는 적하목록관리번호·호출번호 등 해상입항정보 제공으로 기재</td><td class="wait fw-bold">현재 계정에서 승인/요청 가능한 실제 명세 미확보</td></tr>
<tr><td><b>API038</b><br>하선신고 목록조회</td><td>하선신고 목록 정보 제공</td><td class="wait fw-bold">B/L/MSN/cargMtNo 반환 근거 미확인</td></tr>
</tbody></table></div></div></div>
<div class="card shadow-sm mb-3"><div class="card-body"><h5 class="fw-bold">자동화 허용 조건</h5><p class="mb-2">아래 셋 중 하나가 공식 요청·응답 명세 또는 실제 승인 API 응답으로 확인될 때만 자동 연결합니다.</p><ol class="mb-0"><li>MRN → MBL/HBL 목록</li><li>MRN → MSN/HSN 목록</li><li>MRN → 완성된 cargMtNo 목록</li></ol></div></div>
<div class="alert alert-warning"><b>안전장치:</b> MRN 뒤에 0001, 001 같은 번호를 임의로 붙여 API001을 반복 호출하지 않습니다. 화물관리번호 구조상 MSN/HSN은 실제 적하목록의 일련번호이므로 공식 데이터로 확보해야 합니다.</div>
<div class="card shadow-sm"><div class="card-body"><h5 class="fw-bold">다음 운영 단계</h5><div>현재 시스템은 <b>Shanghai → 자동차운반선 → API024 → MRN</b>까지 자동화된 상태입니다. 실제 B/L 또는 cargMtNo가 확보되면 MRN 실험실에서 API001 결과를 즉시 교차검증할 수 있습니다.</div></div></div>
</div></body></html>