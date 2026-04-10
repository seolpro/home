<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$contact = [
    'name'       => trim((string)($_POST['name'] ?? '')),
    'company'    => trim((string)($_POST['company'] ?? '')),
    'job_title'  => trim((string)($_POST['job_title'] ?? '')),
    'department' => trim((string)($_POST['department'] ?? '')),
    'mobile'     => trim((string)($_POST['mobile'] ?? '')),
    'phone'      => trim((string)($_POST['phone'] ?? '')),
    'email'      => trim((string)($_POST['email'] ?? '')),
    'website'    => trim((string)($_POST['website'] ?? '')),
    'address'    => trim((string)($_POST['address'] ?? '')),
    'memo'       => trim((string)($_POST['memo'] ?? '')),
];

$vcf = build_vcf($contact);

$fileBase = $contact['name'] !== ''
    ? $contact['name']
    : ($contact['company'] !== '' ? $contact['company'] : 'contact');

$fileBase = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $fileBase);
$fileBase = trim((string)$fileBase, '_');
if ($fileBase === '') {
    $fileBase = 'contact';
}

$filename = $fileBase . '.vcf';

header('Content-Type: text/x-vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . strlen($vcf));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

echo $vcf;
exit;