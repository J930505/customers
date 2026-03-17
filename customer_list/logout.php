<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/app.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool) ($params['secure'] ?? false),
        'httponly' => true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_unset();
session_destroy();

$loginUrlJs = json_encode(appUrl('login.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登出中 | 客戶名單系統</title>
</head>
<body>
<script>
    try {
        window.sessionStorage.removeItem('customer_list_tab_token');
    } catch (error) {
    }

    window.location.replace(<?= $loginUrlJs ?>);
</script>
<noscript>
    <meta http-equiv="refresh" content="0;url=<?= h(appUrl('login.php')) ?>">
</noscript>
</body>
</html>
