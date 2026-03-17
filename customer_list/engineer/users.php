<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/layout.php';

requireEngineer();

$user = $_SESSION['user'];
$scope = normalizeUserScope($_GET['scope'] ?? 'all');
$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$engineerTotal = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_engineer = 1')->fetchColumn();
$generalTotal = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_engineer = 0')->fetchColumn();

$sql = 'SELECT id, username, password_plain, is_engineer, created_at, updated_at FROM users';
if ($scope === 'engineers') {
    $sql .= ' WHERE is_engineer = 1';
} elseif ($scope === 'general') {
    $sql .= ' WHERE is_engineer = 0';
}
$sql .= ' ORDER BY is_engineer DESC, id ASC';

$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();

$statusMessages = [
    'created' => '帳號已建立。',
    'updated' => '帳號資料已更新。',
    'deleted' => '帳號已刪除。',
    'missing' => '找不到指定的帳號資料。',
    'self_block' => '目前登入的帳號不能刪除。',
    'last_engineer_block' => '系統至少需保留一位工程師帳號。',
    'password_reset' => '密碼已重新設定。',
    'password_invalid' => '密碼至少需要 6 碼。',
];
$status = $_GET['status'] ?? '';
$statusMessage = $statusMessages[$status] ?? '';

renderAppLayoutStart('帳號維護', $user, 'engineer');
?>
<div class="grid gap-6">
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-panel lg:p-7">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between">
            <div class="shrink-0">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">帳號列表</h2>
            </div>
            <div class="flex flex-1 flex-col gap-4 xl:items-end">
                <div class="flex flex-wrap items-center justify-center gap-3 xl:justify-end">
                    <div class="inline-flex flex-wrap rounded-2xl border border-slate-200 bg-slate-50 p-1">
                        <a href="<?= h(userListUrl(['scope' => 'all'])) ?>" class="inline-flex items-center rounded-2xl px-5 py-3 text-sm font-semibold <?= $scope === 'all' ? 'bg-primary-600 text-white shadow-lg shadow-primary-100' : 'text-slate-600 transition hover:text-slate-900' ?>">全部帳號</a>
                        <a href="<?= h(userListUrl(['scope' => 'engineers'])) ?>" class="inline-flex items-center rounded-2xl px-5 py-3 text-sm font-semibold <?= $scope === 'engineers' ? 'bg-primary-600 text-white shadow-lg shadow-primary-100' : 'text-slate-600 transition hover:text-slate-900' ?>">只看工程師</a>
                        <a href="<?= h(userListUrl(['scope' => 'general'])) ?>" class="inline-flex items-center rounded-2xl px-5 py-3 text-sm font-semibold <?= $scope === 'general' ? 'bg-primary-600 text-white shadow-lg shadow-primary-100' : 'text-slate-600 transition hover:text-slate-900' ?>">只看一般帳號</a>
                    </div>
                    <a href="<?= h(userFormUrl(null, ['scope' => $scope])) ?>" class="inline-flex items-center rounded-2xl bg-primary-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-100 transition hover:bg-primary-700">新增帳號</a>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-2 xl:justify-end">
                    <span class="inline-flex items-center rounded-full bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700">系統帳號 <?= $totalUsers ?> 筆</span>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700">工程師 <?= $engineerTotal ?> 筆</span>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600">一般帳號 <?= $generalTotal ?> 筆</span>
                </div>
            </div>
        </div>
    </section>

    <?php if ($statusMessage !== ''): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-base font-medium text-emerald-700">
            <?= h($statusMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!$users): ?>
        <div class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-panel">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="bi bi-person-x text-xl"></i></div>
            <h3 class="mt-4 text-lg font-semibold text-slate-900">目前沒有帳號資料</h3>
            <p class="mt-2 text-sm text-slate-500">可以從右上方新增系統帳號。</p>
        </div>
    <?php else: ?>
        <section class="grid gap-4">
            <?php foreach ($users as $row): ?>
                <?php
                    $isCurrentUser = (int) $row['id'] === (int) $user['id'];
                    $cannotDelete = $isCurrentUser || (!empty($row['is_engineer']) && $engineerTotal <= 1);
                    $visiblePassword = visiblePasswordValue($row['password_plain'] ?? null);
                ?>
                <article class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-panel lg:p-6">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-2xl font-semibold text-slate-900"><?= h($row['username']) ?></h3>
                                <?php if (!empty($row['is_engineer'])): ?>
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">工程師</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">一般帳號</span>
                                <?php endif; ?>
                                <?php if ($isCurrentUser): ?>
                                    <span class="inline-flex items-center rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold text-primary-700">目前登入</span>
                                <?php endif; ?>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">帳號 ID：<?= h((string) $row['id']) ?></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?= h(userFormUrl((int) $row['id'], ['scope' => $scope])) ?>" class="inline-flex items-center rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700">編輯</a>
                            <?php if ($cannotDelete): ?>
                                <button type="button" class="inline-flex items-center rounded-2xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-400" disabled>不可刪除</button>
                            <?php else: ?>
                                <form method="post" action="<?= h(appUrl('engineer/delete.php')) ?>" onsubmit="return confirm('確定要刪除這個帳號嗎？');">
                                    <input type="hidden" name="id" value="<?= h((string) $row['id']) ?>">
                                    <button type="submit" class="inline-flex items-center rounded-2xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 transition hover:bg-rose-50">刪除</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-[180px_180px_minmax(0,1fr)_220px_220px]">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="text-sm font-semibold text-slate-400">帳號</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900"><?= h($row['username']) ?></div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="text-sm font-semibold text-slate-400">角色</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900"><?= !empty($row['is_engineer']) ? '工程師' : '一般帳號' ?></div>
                        </div>
                        <div class="rounded-2xl border px-4 py-4 <?= $visiblePassword === null ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white' ?>">
                            <div class="text-sm font-semibold <?= $visiblePassword === null ? 'text-amber-700' : 'text-slate-400' ?>">登入密碼</div>
                            <div class="mt-2 break-all font-mono text-lg font-semibold <?= $visiblePassword === null ? 'text-amber-800' : 'text-slate-900' ?>"><?= h($visiblePassword ?? '未保存，請重設') ?></div>
                            <?php if ($visiblePassword === null): ?>
                                <div class="mt-2 text-xs leading-6 text-amber-700">舊資料未儲存可見密碼，請進入編輯後重新設定。</div>
                            <?php endif; ?>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="text-sm font-semibold text-slate-400">建立時間</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900"><?= h($row['created_at']) ?></div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="text-sm font-semibold text-slate-400">更新時間</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900"><?= h($row['updated_at'] ?? '-') ?></div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>
<?php renderAppLayoutEnd(); ?>
