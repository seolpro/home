<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../lib/app.php';

try {
    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $year = (int)($_GET['year'] ?? date('Y'));
    $employee = get_employee($employeeId);
    if (!$employee) throw new RuntimeException('직원정보를 찾을 수 없습니다.');

    $granted = grant_for_year($employee, $year);
    $approved = used_for_year($employeeId, $year, false);
    $approvedAndPending = used_for_year($employeeId, $year, true);
    $pending = max(0, $approvedAndPending - $approved);
    $remaining = $granted - $approved;
    $mandatoryRate = $employee['mandatory_rate'] !== null
        ? (float)$employee['mandatory_rate']
        : (float)setting('mandatory_rate', '70');
    $mandatoryDays = $granted * $mandatoryRate / 100;
    $usageRate = $granted > 0 ? round($approved / $granted * 100, 1) : 0;
    $mandatoryRemaining = max(0, $mandatoryDays - $approved);

    echo json_encode([
        'ok' => true,
        'year' => $year,
        'employee_name' => trim((string)$employee['name'] . ' ' . (string)$employee['position']),
        'granted' => $granted,
        'approved' => $approved,
        'pending' => $pending,
        'remaining' => $remaining,
        'usage_rate' => $usageRate,
        'mandatory_rate' => $mandatoryRate,
        'mandatory_days' => round($mandatoryDays, 2),
        'mandatory_remaining' => round($mandatoryRemaining, 2),
        'mandatory_achieved' => $approved + 0.00001 >= $mandatoryDays,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
