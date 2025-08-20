<?php
// ---- CORS: 모든 응답(성공/실패)과 OPTIONS에 헤더 부착 ----
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['http://127.0.0.1:5500', 'http://localhost:5500', 'https://ajou9770.github.io', 'https://ajoucu.mycafe24.com'];
if ($origin && in_array($origin, $allowed, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Vary: Origin");
} else {
  // 테스트 급하면 * 로 두셔도 됩니다. (운영에선 위 화이트리스트 권장)
  // header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");
header("Access-Control-Max-Age: 86400");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// (여기 아래부터 기존 push_to_github.php 본문 로직)

header('Content-Type: application/json; charset=utf-8');

/* ===== 깃허브 설정 (수정) ===== */
$GITHUB_TOKEN = 'ghp_f1a7e6c2d47b9a85e3190b1f54c7d63f98e0a24d7b5c63f2d85c7a19e3f04a61'; // ★ Fine-grained PAT(Repository contents: Read/Write). 서버에만 저장!
$OWNER     = 'seolpro';
$REPO      = 'seolpro.github.io'; // 사용자/조직 페이지 리포
$BRANCH    = 'main';              // 리포 기본 브랜치에 맞게
$BASE_PATH = 'home/uploads';      // ← 여기만 핵심!
                            
/* ===== 간단 인증(선택) — 서버 엔드포인트 남용 방지용 비밀키 ===== */
$ENDPOINT_SECRET = 'set_a_server_side_secret_here';         // 프론트에서 form 필드로 전달해야 호출 가능
if (($_POST['endpoint_secret'] ?? '') !== $ENDPOINT_SECRET) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Forbidden']); exit;
}

/* ===== 요청 유효성 ===== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'POST only']); exit;
}
if (empty($_FILES['file'])) {
  echo json_encode(['ok'=>false,'error'=>'No file']); exit;
}

/* ===== 입력 파라미터 ===== */
$subdir  = trim($_POST['subdir'] ?? '', "/ \t\n\r\0\x0B");      // 리포 내 하위 경로(선택)
$commit  = $_POST['message'] ?? ('Upload ' . date('Y-m-d H:i:s'));
$branch  = $_POST['branch']  ?? $BRANCH;

/* ===== 파일 준비 ===== */
$name     = $_FILES['file']['name'];
$tmp      = $_FILES['file']['tmp_name'];
$err      = $_FILES['file']['error'];
$size     = $_FILES['file']['size'];

if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
  echo json_encode(['ok'=>false,'error'=>'Upload failed']); exit;
}
if ($size > 20 * 1024 * 1024) { // 안전상 20MB 제한(필요시 조정). 큰 파일은 LFS 권장.
  echo json_encode(['ok'=>false,'error'=>'File too large']); exit;
}

/* ===== 경로 구성 & 정화 ===== */
$san = function($s){ return preg_replace('/[^A-Za-z0-9._\-\/]/','_', $s); };
$cleanSub = $san($subdir);
$cleanName= $san($name);
$pathParts = array_filter([$BASE_PATH, $cleanSub, $cleanName], fn($v)=>$v!=='');
$targetPath = implode('/', $pathParts);    // 예: uploads/confer_jeju/index.html

/* ===== 깃허브 Contents API 호출 준비 ===== */
$apiBase = "https://api.github.com";
$apiPath = "/repos/$OWNER/$REPO/contents/" . rawurlencode($targetPath);
$apiUrl  = $apiBase . $apiPath;
$headers = [
  "Authorization: Bearer $GITHUB_TOKEN",
  "Accept: application/vnd.github+json",
  "X-GitHub-Api-Version: 2022-11-28",
  "User-Agent: cafe24-uploader"
];

/* 파일 존재 검사(있으면 sha 필요 → 업데이트, 없으면 생성) */
$sha = null;
$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => $apiUrl . '?ref=' . urlencode($branch),
  CURLOPT_HTTPHEADER => $headers,
  CURLOPT_RETURNTRANSFER => true
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code === 200) {
  $j = json_decode($resp, true);
  $sha = $j['sha'] ?? null; // 업데이트 시 필요
} elseif ($code !== 404) {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>'GitHub check failed','status'=>$code,'body'=>$resp]); exit;
}

/* PUT(생성/업데이트) */
$contentB64 = base64_encode(file_get_contents($tmp));
$payload = [
  'message' => $commit,
  'content' => $contentB64,
  'branch'  => $branch
];
if ($sha) $payload['sha'] = $sha;

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => $apiUrl,
  CURLOPT_CUSTOMREQUEST => 'PUT',
  CURLOPT_HTTPHEADER => array_merge($headers, ['Content-Type: application/json']),
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
  CURLOPT_RETURNTRANSFER => true
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) {
  $j = json_decode($resp, true);
  $htmlUrl = $j['content']['html_url'] ?? null; // 깃허브 웹 UI 파일 링크
  $rawUrl  = "https://raw.githubusercontent.com/$OWNER/$REPO/" . rawurlencode($branch) . "/" . $targetPath;
  echo json_encode(['ok'=>true,'path'=>$targetPath,'html_url'=>$htmlUrl,'raw_url'=>$rawUrl]);
} else {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>'GitHub PUT failed','status'=>$code,'body'=>$resp]);
}
