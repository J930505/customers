<?php

require_once __DIR__ . '/../config/app.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = session_get_cookie_params();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'] ?? '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => (bool) ($cookieParams['secure'] ?? false),
        'httponly' => true,
        'samesite' => $cookieParams['samesite'] ?? 'Lax',
    ]);

    ini_set('session.cookie_lifetime', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_start();
}

function requireLogin(): void
{
    if (empty($_SESSION['user'])) {
        redirectTo('login.php');
    }
}

function requireEngineer(): void
{
    requireLogin();

    if (empty($_SESSION['user']['is_engineer'])) {
        http_response_code(403);
        exit('你沒有權限進入此頁面');
    }
}
