<?php

const APP_NAME = '客戶名單系統';
const APP_BASE_URL = '/customer_list';

function appUrl(string $path = ''): string
{
    $base = rtrim(APP_BASE_URL, '/');
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base . '/';
    }

    return $base . '/' . $path;
}

function assetUrl(string $path): string
{
    return appUrl($path);
}

function redirectTo(string $path = ''): void
{
    header('Location: ' . appUrl($path));
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
