<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/customer_helpers.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$customerId = max(0, (int) ($_POST['customer_id'] ?? 0));
$keyword = trim($_POST['keyword'] ?? '');
$page = max(1, (int) ($_POST['page'] ?? 1));
$logIds = $_POST['log_ids'] ?? [];

if ($customerId <= 0) {
    http_response_code(400);
    exit('Invalid customer id');
}

$deletedCount = deleteCustomerLogs($pdo, $customerId, is_array($logIds) ? $logIds : []);
$status = $deletedCount > 0 ? 'deleted_logs' : 'missing_logs';

header('Location: ' . customerHistoryUrl($customerId, $keyword, $page, [
    'status' => $status,
    'count' => $deletedCount,
]));
exit;