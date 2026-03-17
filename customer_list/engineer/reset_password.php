<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_helpers.php';

requireEngineer();

$id = max(0, (int) ($_POST['id'] ?? 0));
$scope = normalizeUserScope($_POST['scope'] ?? 'all');
$password = (string) ($_POST['password'] ?? '');

if ($id <= 0 || !fetchUser($pdo, $id)) {
    header('Location: ' . userListUrl(['status' => 'missing', 'scope' => $scope]));
    exit;
}

$passwordError = validatePasswordValue($password);
if ($passwordError !== null) {
    header('Location: ' . userListUrl(['status' => 'password_invalid', 'scope' => $scope]));
    exit;
}

updateUserPassword($pdo, $id, $password);
header('Location: ' . userListUrl(['status' => 'password_reset', 'scope' => $scope]));
exit;