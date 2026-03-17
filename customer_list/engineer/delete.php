<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_helpers.php';

requireEngineer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$currentUser = $_SESSION['user'];
$id = max(0, (int) ($_POST['id'] ?? 0));
$status = 'missing';

if ($id > 0) {
    if ($id === (int) $currentUser['id']) {
        $status = 'self_block';
    } else {
        $targetUser = fetchUser($pdo, $id);

        if (!$targetUser) {
            $status = 'missing';
        } elseif (!empty($targetUser['is_engineer']) && engineerCount($pdo, $id) === 0) {
            $status = 'last_engineer_block';
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $status = $stmt->rowCount() > 0 ? 'deleted' : 'missing';
        }
    }
}

header('Location: ' . userListUrl(['status' => $status]));
exit;