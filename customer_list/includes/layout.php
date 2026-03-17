<?php

require_once __DIR__ . '/../config/app.php';

function userRoleLabel(array $user): string
{
    return !empty($user['is_engineer']) ? '工程師' : '一般帳號';
}

function sidebarLink(string $label, string $path, string $icon, bool $active = false): string
{
    $classes = $active
        ? 'flex items-center gap-3 rounded-2xl bg-primary-50 px-4 py-3 text-sm font-semibold text-primary-700 ring-1 ring-primary-100'
        : 'flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900';

    return '<a href="' . h(appUrl($path)) . '" class="' . $classes . '"><i class="bi ' . h($icon) . ' text-base"></i><span>' . h($label) . '</span></a>';
}

function renderSidebarMenu(array $user, string $activeNav): string
{
    $links = [];

    if (!empty($user['is_engineer'])) {
        $links[] = sidebarLink('儀表板', 'index.php', 'bi-grid-1x2', $activeNav === 'home');
    }

    $links[] = sidebarLink('客戶列表', 'customers/index.php', 'bi-people', $activeNav === 'customers');
    $links[] = sidebarLink('新增客戶', 'customers/edit.php', 'bi-plus-circle', $activeNav === 'customer-create');
    $links[] = sidebarLink('Excel 匯入 / 匯出', 'customers/import.php', 'bi-file-earmark-arrow-up', $activeNav === 'import');

    if (!empty($user['is_engineer'])) {
        $links[] = sidebarLink('帳號維護', 'engineer/users.php', 'bi-person-gear', $activeNav === 'engineer');
    }

    return implode('', $links);
}

function renderAppLayoutStart(string $title, array $user, string $activeNav = ''): void
{
    $pageTitle = h($title . ' | ' . APP_NAME);
    $siteCss = h(assetUrl('assets/site.css'));
    $brandUrl = h(!empty($user['is_engineer']) ? appUrl('index.php') : appUrl('customers/index.php'));
    $logoutUrl = h(appUrl('logout.php'));
    $logoutUrlJs = json_encode(appUrl('logout.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $sessionTabTokenJs = json_encode((string) ($_SESSION['tab_token'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $username = h($user['username'] ?? '');
    $roleLabel = h(userRoleLabel($user));
    $sidebarMenu = renderSidebarMenu($user, $activeNav);
    $roleBadge = !empty($user['is_engineer'])
        ? '<span class="inline-flex items-center rounded-full bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">工程師</span>'
        : '<span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">一般帳號</span>';

    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageTitle}</title>
    <script>
        (function () {
            const storageKey = 'customer_list_tab_token';
            const expectedToken = {$sessionTabTokenJs};
            const logoutUrl = {$logoutUrlJs};

            if (!expectedToken) {
                window.location.replace(logoutUrl);
                return;
            }

            try {
                const currentToken = window.sessionStorage.getItem(storageKey);

                if (!currentToken || currentToken !== expectedToken) {
                    window.sessionStorage.removeItem(storageKey);
                    window.location.replace(logoutUrl);
                }
            } catch (error) {
                window.location.replace(logoutUrl);
            }
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a'
                        }
                    },
                    boxShadow: {
                        panel: '0 18px 40px rgba(15, 23, 42, 0.08)'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{$siteCss}">
</head>
<body class="min-h-screen bg-slate-100 text-slate-700">
    <div class="absolute inset-x-0 top-0 -z-10 h-72 bg-gradient-to-br from-primary-100 via-white to-cyan-50"></div>
    <nav class="fixed inset-x-0 top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur">
        <div class="flex w-full items-center justify-between gap-4 px-4 py-3 sm:px-6 xl:px-8">
            <div class="flex items-center gap-4">
                <button type="button" id="sidebar-toggle" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm lg:hidden">
                    <span class="sr-only">開啟選單</span>
                    <i class="bi bi-list text-lg"></i>
                </button>
                <a href="{$brandUrl}" class="text-xl font-bold tracking-[0.01em] text-slate-900 transition hover:text-primary-700">
                    客戶名單系統
                </a>
            </div>
            <div class="flex items-center">
                <a href="{$logoutUrl}" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-7 py-3.5 text-lg font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-900">登出</a>
            </div>
        </div>
    </nav>

    <div id="sidebar-backdrop" class="fixed inset-0 z-20 hidden bg-slate-900/35 lg:hidden"></div>

    <aside id="app-sidebar" class="fixed left-0 top-0 z-30 h-screen w-72 -translate-x-full border-r border-slate-200 bg-white pt-20 transition-transform duration-200 ease-out lg:translate-x-0" aria-label="側邊選單">
        <div class="flex h-full flex-col overflow-y-auto px-4 pb-6">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="truncate text-[1.75rem] font-bold leading-none tracking-[0.01em] text-slate-900">{$username}</div>
                        <div class="mt-2 text-sm font-medium text-slate-500">{$roleLabel}</div>
                    </div>
                    <div class="h-3 w-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></div>
                </div>
            </div>
            <div class="mt-6 space-y-2">{$sidebarMenu}</div>
        </div>
    </aside>

    <main class="pt-20 lg:pl-72">
        <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 px-4 py-4 sm:px-6 xl:px-8 xl:py-5">
HTML;
}

function renderAppLayoutEnd(): void
{
    echo <<<'HTML'
        </div>
    </main>
    <script>
        (function () {
            const sidebar = document.getElementById('app-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const toggle = document.getElementById('sidebar-toggle');

            if (!sidebar || !backdrop || !toggle) {
                return;
            }

            const openSidebar = () => {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
            };

            const closeSidebar = () => {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
            };

            toggle.addEventListener('click', () => {
                if (window.innerWidth >= 1024) {
                    return;
                }

                if (sidebar.classList.contains('-translate-x-full')) {
                    openSidebar();
                } else {
                    closeSidebar();
                }
            });

            backdrop.addEventListener('click', closeSidebar);
        })();
    </script>
</body>
</html>
HTML;
}

function renderGuestLayoutStart(string $title): void
{
    $pageTitle = h($title . ' | ' . APP_NAME);
    $siteCss = h(assetUrl('assets/site.css'));

    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageTitle}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a'
                        }
                    },
                    boxShadow: {
                        panel: '0 18px 40px rgba(15, 23, 42, 0.08)'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{$siteCss}">
</head>
<body class="min-h-screen bg-slate-100 text-slate-700">
    <div class="absolute inset-x-0 top-0 -z-10 h-80 bg-gradient-to-br from-primary-100 via-white to-cyan-50"></div>
    <main class="mx-auto flex min-h-screen w-full max-w-[1320px] items-center px-4 py-8 sm:px-6 xl:px-8">
HTML;
}

function renderGuestLayoutEnd(): void
{
    echo <<<'HTML'
    </main>
</body>
</html>
HTML;
}
