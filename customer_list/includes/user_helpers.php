<?php

require_once __DIR__ . '/../config/app.php';

function userListUrl(array $extra = []): string
{
    $query = http_build_query(array_filter($extra, static fn($value): bool => $value !== '' && $value !== null));

    return $query === '' ? appUrl('engineer/users.php') : appUrl('engineer/users.php') . '?' . $query;
}

function userFormUrl(?int $id = null, array $extra = []): string
{
    $query = $extra;

    if ($id !== null) {
        $query['id'] = $id;
    }

    $queryString = http_build_query(array_filter($query, static fn($value): bool => $value !== '' && $value !== null));

    return $queryString === '' ? appUrl('engineer/edit.php') : appUrl('engineer/edit.php') . '?' . $queryString;
}

function userPasswordResetUrl(): string
{
    return appUrl('engineer/reset_password.php');
}

function normalizeUserScope(?string $scope): string
{
    return in_array($scope, ['all', 'engineers', 'general'], true) ? $scope : 'all';
}

function fetchUser(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, password_hash, password_plain, is_engineer, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function engineerCount(PDO $pdo, ?int $excludeId = null): int
{
    $sql = 'SELECT COUNT(*) FROM users WHERE is_engineer = 1';
    $params = [];

    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function validatePasswordValue(string $password): ?string
{
    if ($password === '') {
        return '請輸入密碼。';
    }

    if (strlen($password) < 6) {
        return '密碼長度至少需要 6 個字元。';
    }

    return null;
}

function validateUserPayload(PDO $pdo, array $payload, bool $isCreate, ?int $excludeId = null): array
{
    $errors = [];
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');

    if ($username === '') {
        $errors[] = '帳號為必填欄位。';
    }

    if ($isCreate) {
        $passwordError = validatePasswordValue($password);
        if ($passwordError !== null) {
            $errors[] = $passwordError;
        }
    } elseif ($password !== '') {
        $passwordError = validatePasswordValue($password);
        if ($passwordError !== null) {
            $errors[] = $passwordError;
        }
    }

    if ($username !== '') {
        $sql = 'SELECT id FROM users WHERE username = ?';
        $params = [$username];

        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        if ($stmt->fetch()) {
            $errors[] = '這個帳號已經存在，請改用其他帳號名稱。';
        }
    }

    return $errors;
}

function persistUser(PDO $pdo, array $payload, bool $isCreate, ?int $id = null): int
{
    $username = trim((string) ($payload['username'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    $isEngineer = !empty($payload['is_engineer']) ? 1 : 0;

    if ($isCreate) {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, password_plain, is_engineer) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $password, $isEngineer]);

        return (int) $pdo->lastInsertId();
    }

    if ($password !== '') {
        $stmt = $pdo->prepare('UPDATE users SET username = ?, password_hash = ?, password_plain = ?, is_engineer = ? WHERE id = ?');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $password, $isEngineer, $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET username = ?, is_engineer = ? WHERE id = ?');
        $stmt->execute([$username, $isEngineer, $id]);
    }

    return (int) $id;
}

function updateUserPassword(PDO $pdo, int $id, string $password): bool
{
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, password_plain = ? WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $password, $id]);

    return $stmt->rowCount() > 0;
}

function visiblePasswordValue(?string $passwordPlain): ?string
{
    if ($passwordPlain === null) {
        return null;
    }

    $passwordPlain = trim($passwordPlain);

    return $passwordPlain === '' ? null : $passwordPlain;
}