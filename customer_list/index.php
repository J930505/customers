<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/user_helpers.php';

requireLogin();

if (empty($_SESSION['user']['is_engineer'])) {
    redirectTo('customers/index.php');
}

$user = $_SESSION['user'];
$customerTotal = (int) $pdo->query('SELECT COUNT(*) FROM customers WHERE is_deleted = 0')->fetchColumn();
$userTotal = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$engineerTotal = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_engineer = 1')->fetchColumn();
$latestNotifyDate = $pdo->query('SELECT MAX(notify_date) FROM customers WHERE is_deleted = 0')->fetchColumn();

renderAppLayoutStart('儀表板', $user, 'home');
?>
<section class="grid gap-4 xl:grid-cols-2">
    <div class="rounded-[2rem] border border-slate-200 bg-white px-6 py-7 shadow-panel lg:px-7 lg:py-8">
        <div class="flex h-full flex-col justify-center gap-4 text-center xl:text-left">
            <div class="text-sm font-semibold tracking-[0.18em] text-slate-400">最新通知日期</div>
            <div class="text-4xl font-bold tracking-tight text-slate-900 lg:text-5xl"><?= h($latestNotifyDate ?: '目前沒有資料') ?></div>
        </div>
    </div>
    <div class="rounded-[2rem] border border-slate-200 bg-white px-6 py-7 shadow-panel lg:px-7 lg:py-8">
        <div class="flex h-full flex-col justify-center gap-4 text-center xl:text-left">
            <div class="text-sm font-semibold tracking-[0.18em] text-slate-400">頁面權限</div>
            <div class="text-3xl font-bold tracking-tight text-slate-900 lg:text-4xl">管理著專業儀表板</div>
        </div>
    </div>
</section>

<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <a href="<?= h(appUrl('customers/index.php')) ?>" class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-panel transition hover:-translate-y-0.5 hover:border-primary-200 hover:bg-primary-50/40">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">啟用中的客戶</div>
                <div class="mt-2 text-3xl font-bold tracking-tight text-slate-900"><?= $customerTotal ?></div>
            </div>
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-50 text-primary-600"><i class="bi bi-people"></i></span>
        </div>
        <p class="mt-4 text-sm leading-6 text-slate-500">點擊查看客戶列表與完整客戶明細。</p>
    </a>
    <a href="<?= h(userListUrl(['scope' => 'all'])) ?>" class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-panel transition hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-emerald-50/40">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">系統帳號數</div>
                <div class="mt-2 text-3xl font-bold tracking-tight text-slate-900"><?= $userTotal ?></div>
            </div>
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><i class="bi bi-person-badge"></i></span>
        </div>
        <p class="mt-4 text-sm leading-6 text-slate-500">點擊查看全部帳號明細與密碼保存資訊。</p>
    </a>
    <a href="<?= h(userListUrl(['scope' => 'engineers'])) ?>" class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-panel transition hover:-translate-y-0.5 hover:border-amber-200 hover:bg-amber-50/40">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500">工程師帳號</div>
                <div class="mt-2 text-3xl font-bold tracking-tight text-slate-900"><?= $engineerTotal ?></div>
            </div>
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600"><i class="bi bi-person-gear"></i></span>
        </div>
        <p class="mt-4 text-sm leading-6 text-slate-500">點擊查看工程師帳號明細與密碼保存資訊。</p>
    </a>
</section>
<?php renderAppLayoutEnd(); ?>
