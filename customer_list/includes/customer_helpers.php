<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/customer_fields.php';

function customerListUrl(string $keyword = '', int $page = 1, array $extra = []): string
{
    $query = array_filter(array_merge([
        'keyword' => $keyword,
        'page' => $page,
    ], $extra), static fn($value): bool => $value !== '' && $value !== null);

    $queryString = http_build_query($query);

    return $queryString === '' ? appUrl('customers/index.php') : appUrl('customers/index.php') . '?' . $queryString;
}

function customerFormUrl(?int $id = null, string $keyword = '', int $page = 1): string
{
    $query = [
        'keyword' => $keyword,
        'page' => $page,
    ];

    if ($id !== null) {
        $query['id'] = $id;
    }

    return appUrl('customers/edit.php') . '?' . http_build_query($query);
}

function customerHistoryUrl(int $id, string $keyword = '', int $page = 1, array $extra = []): string
{
    $query = array_merge([
        'id' => $id,
        'keyword' => $keyword,
        'page' => $page,
    ], $extra);

    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null);

    return appUrl('customers/history.php') . '?' . http_build_query($query);
}

function fetchCustomerRecord(PDO $pdo, int $id, bool $includeDeleted = false): ?array
{
    $selectColumns = array_unique(array_merge(customerFieldNames(), ['receipt_two']));
    $sql = 'SELECT id, ' . implode(', ', $selectColumns) . ', is_deleted, created_by, updated_by, deleted_by, created_at, updated_at, deleted_at FROM customers WHERE id = ?';

    if (!$includeDeleted) {
        $sql .= ' AND is_deleted = 0';
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return normalizeReceiptColumns($row);
}

function fetchCustomer(PDO $pdo, int $id): ?array
{
    return fetchCustomerRecord($pdo, $id, false);
}

function fetchCustomerSnapshot(PDO $pdo, int $id, bool $includeDeleted = false): ?array
{
    $record = fetchCustomerRecord($pdo, $id, $includeDeleted);

    if (!$record) {
        return null;
    }

    $snapshot = [];

    foreach (customerFieldNames() as $fieldName) {
        $snapshot[$fieldName] = $record[$fieldName] ?? null;
    }

    return $snapshot;
}

function emptyCustomerData(): array
{
    $data = [];

    foreach (customerFieldDefinitions() as $field) {
        $data[$field['name']] = '';
    }

    return $data;
}

function normalizeCustomerWideText(string $value): string
{
    if (function_exists('mb_convert_kana')) {
        $value = mb_convert_kana($value, 'as', 'UTF-8');
    }

    return str_replace(
        ["\u{FF0C}", "\u{FF0E}", "\u{FF0F}", "\u{FF0D}", "\u{FF0B}", "\u{FF08}", "\u{FF09}", "\u{3000}", "\xC2\xA0"],
        [',', '.', '/', '-', '+', '(', ')', ' ', ' '],
        $value
    );
}

function normalizeCustomerDecimalString($value): string
{
    if (!is_numeric($value)) {
        return trim((string) $value);
    }

    $normalized = number_format((float) $value, 2, '.', '');
    $normalized = rtrim(rtrim($normalized, '0'), '.');

    if ($normalized === '' || $normalized === '-0') {
        return '0';
    }

    return $normalized;
}

function formatMonthlyFeeValue($value, bool $useThousands = true): string
{
    $normalized = normalizeCustomerDecimalString($value);

    if ($normalized === '' || !is_numeric($normalized)) {
        return $normalized;
    }

    $decimals = str_contains($normalized, '.') ? strlen((string) substr(strrchr($normalized, '.'), 1)) : 0;

    return number_format((float) $normalized, $decimals, '.', $useThousands ? ',' : '');
}

function customerFormValue(string $fieldName, $value): string
{
    if ($value === null) {
        return '';
    }

    if (is_string($value)) {
        $value = trim($value);
    }

    if ($value === '') {
        return '';
    }

    if ($fieldName === 'monthly_fee' && is_numeric($value)) {
        return formatMonthlyFeeValue($value, false);
    }

    return (string) $value;
}

function normalizeCustomerInput(array $field, string $rawValue)
{
    $value = trim($rawValue);

    if ($value === '') {
        return null;
    }

    if (($field['input'] ?? '') === 'date') {
        return normalizeCustomerDateValue($value);
    }

    if (($field['input'] ?? '') === 'number') {
        $numericValue = normalizeCustomerNumberValue($field, $value);

        if ($numericValue === false) {
            return false;
        }

        if (($field['name'] ?? '') === 'monthly_fee' || ($field['step'] ?? '') === '0.01') {
            return normalizeCustomerDecimalString($numericValue);
        }

        return (string) (int) round((float) $numericValue);
    }

    return $value;
}

function normalizeCustomerNumberValue(array $field, string $value)
{
    $normalizedValue = normalizeCustomerWideText(trim($value));
    $normalizedValue = str_replace(' ', '', $normalizedValue);

    $isNegativeByParentheses = false;
    if (preg_match('/^\((.+)\)$/', $normalizedValue, $matches) === 1) {
        $normalizedValue = $matches[1];
        $isNegativeByParentheses = true;
    }

    if (($field['name'] ?? '') === 'monthly_fee') {
        $normalizedValue = preg_replace('/^(?:ntd|nt\$|twd|usd|us\$|\$)+/iu', '', $normalizedValue);
        $normalizedValue = str_replace(["\u{00A5}", "\u{FFE5}"], '', $normalizedValue);
        $normalizedValue = str_replace([
            "/\u{6708}",
            "\u{FF0F}\u{6708}",
            "\u{6BCF}\u{6708}",
            "\u{6708}\u{8CBB}",
            "\u{5143}",
            "\u{5713}",
            "\u{584A}",
        ], '', $normalizedValue);
        $normalizedValue = preg_replace('/[^\d.\-]/u', '', (string) $normalizedValue) ?? '';
    }

    $normalizedValue = str_replace(',', '', $normalizedValue);

    if ($isNegativeByParentheses) {
        $normalizedValue = '-' . $normalizedValue;
    }

    if ($normalizedValue === '') {
        return false;
    }

    if (!preg_match('/^-?\d+(?:\.\d+)?$/', $normalizedValue)) {
        return false;
    }

    return $normalizedValue;
}

function normalizeCustomerDateValue(string $value)
{
    $originalValue = normalizeCustomerWideText(trim($value));
    $numericCandidate = str_replace([',', ' '], '', $originalValue);

    if ($numericCandidate !== '' && is_numeric($numericCandidate)) {
        return normalizeExcelSerialDate((float) $numericCandidate);
    }

    $rawValue = str_replace([
        "\u{5E74}",
        "\u{6708}",
        "\u{65E5}",
        '.',
        '-',
        '/',
    ], ['/', '/', '', '/', '/', '/'], $originalValue);
    $rawValue = preg_replace('/\s+/u', ' ', $rawValue);
    $rawValue = trim((string) $rawValue);
    $numericValue = str_replace([',', ' '], '', $rawValue);

    if (preg_match('/^(\d{8})$/', $numericValue, $matches)) {
        return normalizeDateParts(
            (int) substr($matches[1], 0, 4),
            (int) substr($matches[1], 4, 2),
            (int) substr($matches[1], 6, 2)
        );
    }

    if (preg_match('/^(\d{7})$/', $numericValue, $matches)) {
        return normalizeDateParts(
            (int) substr($matches[1], 0, 3) + 1911,
            (int) substr($matches[1], 3, 2),
            (int) substr($matches[1], 5, 2)
        );
    }

    if (preg_match('/^(\d{4})[\/](\d{1,2})[\/](\d{1,2})/', $rawValue, $matches)) {
        return normalizeDateParts((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    if (preg_match('/^(\d{3})[\/](\d{1,2})[\/](\d{1,2})/', $rawValue, $matches)) {
        return normalizeDateParts((int) $matches[1] + 1911, (int) $matches[2], (int) $matches[3]);
    }

    $timestamp = strtotime($rawValue);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return false;
}

function normalizeExcelSerialDate(float $serial)
{
    $days = (int) floor($serial);

    if ($days <= 0) {
        return false;
    }

    $timestamp = ($days - 25569) * 86400;

    return gmdate('Y-m-d', $timestamp);
}

function normalizeDateParts(int $year, int $month, int $day)
{
    if (!checkdate($month, $day, $year)) {
        return false;
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function normalizeReceiptColumns(array $row): array
{
    $row['receipt_one'] = mergeReceiptValues($row['receipt_one'] ?? null, $row['receipt_two'] ?? null);

    return $row;
}

function mergeReceiptValues($primary, $secondary): ?string
{
    $values = [];

    foreach ([$primary, $secondary] as $value) {
        if ($value === null) {
            continue;
        }

        $text = trim((string) $value);
        if ($text === '' || in_array($text, $values, true)) {
            continue;
        }

        $values[] = $text;
    }

    if (!$values) {
        return null;
    }

    return implode(' / ', $values);
}

function prepareCustomerData(array $source): array
{
    $prepared = [];
    $errors = [];

    foreach (customerFieldDefinitions() as $field) {
        $fieldName = $field['name'];
        $normalized = normalizeCustomerInput($field, (string) ($source[$fieldName] ?? ''));

        if ($normalized === false) {
            $errors[] = $field['label'] . " \u{683C}\u{5F0F}\u{4E0D}\u{6B63}\u{78BA}\u{3002}";
            $prepared[$fieldName] = $source[$fieldName] ?? '';
            continue;
        }

        $prepared[$fieldName] = $normalized;
    }

    if (($prepared['customer_code'] ?? null) === null) {
        $errors[] = "\u{5BA2}\u{6236}\u{4EE3}\u{865F}\u{70BA}\u{5FC5}\u{586B}\u{6B04}\u{4F4D}\u{3002}";
    }

    if (($prepared['customer_name'] ?? null) === null) {
        $errors[] = "\u{5BA2}\u{6236}\u{540D}\u{7A31}\u{70BA}\u{5FC5}\u{586B}\u{6B04}\u{4F4D}\u{3002}";
    }

    return [$prepared, $errors];
}

function findCustomerIdByCode(PDO $pdo, string $customerCode, ?int $excludeId = null): ?int
{
    $sql = 'SELECT id FROM customers WHERE customer_code = ? AND is_deleted = 0';
    $params = [$customerCode];

    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();

    return $value === false ? null : (int) $value;
}

function encodeCustomerLogData(?array $data): ?string
{
    if ($data === null) {
        return null;
    }

    $snapshot = [];

    foreach (customerFieldNames() as $fieldName) {
        $snapshot[$fieldName] = $data[$fieldName] ?? null;
    }

    return json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function decodeCustomerLogData($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function writeCustomerLog(PDO $pdo, int $customerId, string $actionType, ?array $oldData, ?array $newData, ?int $userId): void
{
    $stmt = $pdo->prepare('INSERT INTO customer_logs (customer_id, action_type, old_data, new_data, operated_by) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $customerId,
        $actionType,
        encodeCustomerLogData($oldData),
        encodeCustomerLogData($newData),
        $userId,
    ]);
}

function persistCustomer(PDO $pdo, array $data, int $userId, ?int $id = null): int
{
    $fields = customerFieldNames();
    $startedTransaction = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        if ($id === null) {
            $columns = $fields;
            $values = [];

            foreach ($fields as $fieldName) {
                $values[] = $data[$fieldName] ?? null;
            }

            if (!in_array('receipt_two', $fields, true)) {
                $columns[] = 'receipt_two';
                $values[] = null;
            }

            $columns[] = 'created_by';
            $columns[] = 'updated_by';
            $values[] = $userId;
            $values[] = $userId;

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $stmt = $pdo->prepare('INSERT INTO customers (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
            $stmt->execute($values);

            if ($startedTransaction) {
                $pdo->commit();
            }

            return (int) $pdo->lastInsertId();
        }

        $oldData = fetchCustomerSnapshot($pdo, $id);
        if ($oldData === null) {
            throw new RuntimeException("\u{627E}\u{4E0D}\u{5230}\u{6307}\u{5B9A}\u{7684}\u{5BA2}\u{6236}\u{8CC7}\u{6599}\u{3002}");
        }

        $assignments = [];
        $values = [];

        foreach ($fields as $fieldName) {
            $assignments[] = $fieldName . ' = ?';
            $values[] = $data[$fieldName] ?? null;
        }

        if (!in_array('receipt_two', $fields, true)) {
            $assignments[] = 'receipt_two = ?';
            $values[] = null;
        }

        $values[] = $userId;
        $values[] = $id;

        $stmt = $pdo->prepare('UPDATE customers SET ' . implode(', ', $assignments) . ', updated_by = ? WHERE id = ? AND is_deleted = 0');
        $stmt->execute($values);

        if (customerLogChanges($oldData, $data)) {
            writeCustomerLog($pdo, $id, 'update', $oldData, $data, $userId);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $id;
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function softDeleteCustomer(PDO $pdo, int $id, int $userId): bool
{
    $stmt = $pdo->prepare('UPDATE customers SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id = ? AND is_deleted = 0');
    $stmt->execute([$userId, $userId, $id]);

    return $stmt->rowCount() > 0;
}

function customerDisplayValue(string $fieldName, $value): string
{
    if ($value === null) {
        return '-';
    }

    if (is_string($value)) {
        $value = trim($value);
    }

    if ($value === '') {
        return '-';
    }

    if ($fieldName === 'monthly_fee' && is_numeric($value)) {
        return formatMonthlyFeeValue($value, true);
    }

    return (string) $value;
}

function customerLogChanges(array $oldData, array $newData): array
{
    $changes = [];

    foreach (customerFieldDefinitions() as $field) {
        $fieldName = $field['name'];
        $oldValue = customerDisplayValue($fieldName, $oldData[$fieldName] ?? null);
        $newValue = customerDisplayValue($fieldName, $newData[$fieldName] ?? null);

        if ($oldValue !== $newValue) {
            $changes[] = [
                'label' => $field['label'],
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }
    }

    return $changes;
}

function deleteCustomerLogs(PDO $pdo, int $customerId, array $logIds): int
{
    $logIds = array_values(array_filter(array_map('intval', $logIds), static fn(int $value): bool => $value > 0));

    if (!$logIds) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($logIds), '?'));
    $params = array_merge([$customerId], $logIds);
    $stmt = $pdo->prepare('DELETE FROM customer_logs WHERE customer_id = ? AND id IN (' . $placeholders . ')');
    $stmt->execute($params);

    return $stmt->rowCount();
}