<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/customer_helpers.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$user = $_SESSION['user'];
$keyword = trim($_POST['keyword'] ?? '');
$page = max(1, (int) ($_POST['page'] ?? 1));
$ids = $_POST['ids'] ?? [];
$singleId = max(0, (int) ($_POST['id'] ?? 0));

if ($singleId > 0) {
    $ids[] = $singleId;
}

$ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []), static fn(int $value): bool => $value > 0)));

if (!$ids) {
    header('Location: ' . customerListUrl($keyword, $page, ['status' => 'missing_selection']));
    exit;
}

$deletedCount = 0;

foreach ($ids as $id) {
    if (softDeleteCustomer($pdo, $id, (int) $user['id'])) {
        $deletedCount++;
    }
}

$status = $deletedCount > 1 ? 'batch_deleted' : ($deletedCount === 1 ? 'deleted' : 'missing');

header('Location: ' . customerListUrl($keyword, $page, [
    'status' => $status,
    'count' => $deletedCount,
]));
exit;