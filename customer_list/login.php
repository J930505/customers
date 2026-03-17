<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';

$logoutUrl = appUrl('logout.php');

if (!empty($_SESSION['user'])) {
    $redirectUrl = !empty($_SESSION['user']['is_engineer']) ? appUrl('index.php') : appUrl('customers/index.php');
    $sessionTabTokenJs = json_encode((string) ($_SESSION['tab_token'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $redirectUrlJs = json_encode($redirectUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $logoutUrlJs = json_encode($logoutUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    renderGuestLayoutStart('登入');
    echo <<<HTML
<div class="mx-auto w-full max-w-[680px]">
    <section class="rounded-[2.25rem] border border-slate-200 bg-white p-8 text-center shadow-panel sm:p-10 lg:p-12">
        <div class="text-sm font-semibold uppercase tracking-[0.2em] text-primary-600">Login</div>
        <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">正在檢查登入狀態</h1>
        <p class="mt-3 text-base text-slate-500">如果目前分頁的登入仍然有效，系統會自動帶你回原本頁面。</p>
    </section>
</div>
<script>
    (function () {
        const storageKey = 'customer_list_tab_token';
        const expectedToken = {$sessionTabTokenJs};
        const redirectUrl = {$redirectUrlJs};
        const logoutUrl = {$logoutUrlJs};

        try {
            const currentToken = window.sessionStorage.getItem(storageKey);

            if (expectedToken && currentToken === expectedToken) {
                window.location.replace(redirectUrl);
                return;
            }

            window.sessionStorage.removeItem(storageKey);
        } catch (error) {
        }

        window.location.replace(logoutUrl);
    })();
</script>
HTML;
    renderGuestLayoutEnd();
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $tabToken = trim($_POST['tab_token'] ?? '');

    $stmt = $pdo->prepare('SELECT id, username, password_hash, is_engineer FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($tabToken === '') {
            $tabToken = bin2hex(random_bytes(16));
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'is_engineer' => (int) $user['is_engineer'],
        ];
        $_SESSION['tab_token'] = $tabToken;

        redirectTo(!empty($user['is_engineer']) ? 'index.php' : 'customers/index.php');
    }

    $error = '帳號或密碼錯誤。';
}

renderGuestLayoutStart('登入');
?>
<div class="mx-auto w-full max-w-[680px]">
    <section class="rounded-[2.25rem] border border-slate-200 bg-white p-8 shadow-panel sm:p-10 lg:p-12">
        <div>
            <div class="text-sm font-semibold uppercase tracking-[0.2em] text-primary-600">Login</div>
            <h1 class="mt-4 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">客戶名單系統</h1>
            <p class="mt-3 text-base text-slate-500">登入系統</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="mt-7 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-base font-medium text-rose-700">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="mt-9 space-y-6">
            <input id="tab_token" type="hidden" name="tab_token" value="">
            <div>
                <label for="username" class="mb-3 block text-base font-semibold text-slate-700">帳號</label>
                <input id="username" type="text" name="username" class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-base text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100" placeholder="請輸入帳號" required autofocus>
            </div>
            <div>
                <label for="password" class="mb-3 block text-base font-semibold text-slate-700">密碼</label>
                <input id="password" type="password" name="password" class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-base text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100" placeholder="請輸入密碼" required>
            </div>
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-primary-600 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-primary-200 transition hover:bg-primary-700">登入系統</button>
        </form>
    </section>
</div>
<script>
    (function () {
        const storageKey = 'customer_list_tab_token';
        const field = document.getElementById('tab_token');

        if (!field) {
            return;
        }

        let token = '';

        try {
            token = window.crypto && typeof window.crypto.randomUUID === 'function'
                ? window.crypto.randomUUID()
                : String(Date.now()) + Math.random().toString(16).slice(2);
            window.sessionStorage.setItem(storageKey, token);
        } catch (error) {
            token = String(Date.now()) + Math.random().toString(16).slice(2);
        }

        field.value = token;
    })();
</script>
<?php renderGuestLayoutEnd(); ?>
