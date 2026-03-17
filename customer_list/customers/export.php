<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/customer_fields.php';
require_once __DIR__ . '/../includes/customer_helpers.php';

requireLogin();

function normalizeExportIds($rawIds): array
{
    $values = is_array($rawIds) ? $rawIds : [];

    return array_values(array_unique(array_filter(
        array_map('intval', $values),
        static fn(int $value): bool => $value > 0
    )));
}

$keyword = trim((string) ($_POST['keyword'] ?? $_GET['keyword'] ?? ''));
$page = max(1, (int) ($_POST['page'] ?? $_GET['page'] ?? 1));
$selectedIds = normalizeExportIds($_POST['ids'] ?? $_GET['ids'] ?? []);
$isSelectedExport = $_SERVER['REQUEST_METHOD'] === 'POST' || $selectedIds !== [];

$fields = customerFieldDefinitions();
$columns = customerFieldNames();
$selectColumns = implode(', ', array_merge($columns, ['receipt_two']));

if ($isSelectedExport) {
    if (!$selectedIds) {
        header('Location: ' . customerListUrl($keyword, $page, ['status' => 'missing_export_selection']));
        exit;
    }

    $placeholders = implode(', ', array_fill(0, count($selectedIds), '?'));
    $stmt = $pdo->prepare('SELECT ' . $selectColumns . ' FROM customers WHERE is_deleted = 0 AND id IN (' . $placeholders . ') ORDER BY item_no ASC, id ASC');
    $stmt->execute($selectedIds);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        header('Location: ' . customerListUrl($keyword, $page, ['status' => 'missing_export_selection']));
        exit;
    }

    $filenamePrefix = 'customers_selected_';
} else {
    $stmt = $pdo->query('SELECT ' . $selectColumns . ' FROM customers WHERE is_deleted = 0 ORDER BY item_no ASC, id ASC');
    $rows = $stmt->fetchAll();
    $filenamePrefix = 'customers_all_';
}

$filename = $filenamePrefix . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'wb');
fputcsv($output, array_map(static fn(array $field): string => $field['label'], $fields));

foreach ($rows as $row) {
    $row['receipt_one'] = mergeReceiptValues($row['receipt_one'] ?? null, $row['receipt_two'] ?? null);
    $exportRow = [];

    foreach ($fields as $field) {
        $fieldName = $field['name'];
        $value = $row[$fieldName] ?? null;

        if ($value === null) {
            $exportRow[] = '';
            continue;
        }

        if ($fieldName === 'monthly_fee' && is_numeric($value)) {
            $exportRow[] = customerFormValue($fieldName, $value);
            continue;
        }

        $exportRow[] = trim((string) $value);
    }

    fputcsv($output, $exportRow);
}

fclose($output);
exit;