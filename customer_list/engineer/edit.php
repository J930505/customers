<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/layout.php';

requireEngineer();

$currentUser = $_SESSION['user'];
$id = max(0, (int) ($_POST['id'] ?? $_GET['id'] ?? 0));
$scope = normalizeUserScope($_POST['scope'] ?? $_GET['scope'] ?? 'all');
$isCreate = $id === 0;
$errors = [];
$formData = [
    'username' => '',
    'password' => '',
    'is_engineer' => 0,
];
$editingUser = null;

if (!$isCreate) {
    $editingUser = fetchUser($pdo, $id);

    if (!$editingUser) {
        http_response_code(404);
        exit('找不到指定的帳號資料。');
    }

    $formData['username'] = $editingUser['username'];
    $formData['is_engineer'] = (int) $editingUser['is_engineer'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['username'] = trim((string) ($_POST['username'] ?? ''));
    $formData['password'] = trim((string) ($_POST['password'] ?? ''));
    $formData['is_engineer'] = !empty($_POST['is_engineer']) ? 1 : 0;

    $errors = validateUserPayload($pdo, $formData, $isCreate, $isCreate ? null : $id);

    if (!$isCreate && $editingUser && !empty($editingUser['is_engineer']) && empty($formData['is_engineer']) && engineerCount($pdo, $id) === 0) {
        $errors[] = '系統至少要保留一位工程師帳號。';
    }

    if (!$errors) {
        persistUser($pdo, $formData, $isCreate, $isCreate ? null : $id);

        if (!$isCreate && $id === (int) $currentUser['id']) {
            $_SESSION['user']['username'] = $formData['username'];
            $_SESSION['user']['is_engineer'] = (int) $formData['is_engineer'];

            if (empty($_SESSION['user']['is_engineer'])) {
                header('Location: ' . appUrl('customers/index.php'));
                exit;
            }
        }

        header('Location: ' . userListUrl(['status' => $isCreate ? 'created' : 'updated', 'scope' => $scope]));
        exit;
    }
}

$currentVisiblePassword = !$isCreate && $editingUser ? visiblePasswordValue($editingUser['password_plain'] ?? null) : null;
$pageTitle = $isCreate ? '新增帳號' : '編輯帳號';

renderAppLayoutStart($pageTitle, $_SESSION['user'], 'engineer');
?>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-panel lg:p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-slate-900"><?= h($pageTitle) ?></h2>
                <p class="mt-3 max-w-2xl text-base leading-7 text-slate-500">工程師可以在這裡新增或調整系統帳號。當你輸入新密碼並儲存後，帳號維護列表會同步顯示這組可見密碼。</p>
            </div>
            <a href="<?= h(userListUrl(['scope' => $scope])) ?>" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">回帳號列表</a>
        </div>

        <?php if ($errors): ?>
            <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-base text-rose-700">
                <ul class="list-disc space-y-1 pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="mt-7 space-y-6">
            <input type="hidden" name="id" value="<?= h($id) ?>">
            <input type="hidden" name="scope" value="<?= h($scope) ?>">

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">帳號</label>
                    <input id="username" type="text" name="username" value="<?= h($formData['username']) ?>" class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-base text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100" required>
                </div>
                <div>
                    <label for="password" class="mb-2 block text-sm font-semibold text-slate-700"><?= $isCreate ? '登入密碼' : '新密碼（留空代表不變更）' ?></label>
                    <input id="password" type="text" name="password" value="<?= h($formData['password']) ?>" class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-base text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100" placeholder="至少 6 個字元" autocomplete="new-password"<?= $isCreate ? ' required' : '' ?>>
                    <p class="mt-2 text-sm leading-6 text-slate-500">若有輸入新密碼，系統會同時更新登入雜湊與工程師可見的密碼欄位。</p>
                </div>
            </div>

            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-base text-slate-700">
                <input type="checkbox" name="is_engineer" value="1" class="mt-1 h-5 w-5 rounded border-slate-300 text-primary-600 focus:ring-primary-500"<?= !empty($formData['is_engineer']) ? ' checked' : '' ?>>
                <span>
                    <span class="block font-semibold text-slate-900">設為工程師帳號</span>
                    <span class="mt-1 block text-sm leading-6 text-slate-500">工程師可進入儀表板、帳號維護與其他管理頁面。</span>
                </span>
            </label>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="inline-flex items-center rounded-2xl bg-primary-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-primary-100 transition hover:bg-primary-700"><?= $isCreate ? '建立帳號' : '儲存變更' ?></button>
                <a href="<?= h(userListUrl(['scope' => $scope])) ?>" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-6 py-3 text-base font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">取消</a>
            </div>
        </form>
    </section>

    <aside class="space-y-6">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-panel">
            <h3 class="text-xl font-semibold text-slate-900">目前保存的可見密碼</h3>
            <?php if ($isCreate): ?>
                <p class="mt-3 text-base leading-7 text-slate-500">新增帳號後，這裡會顯示該帳號目前保存的可見密碼。</p>
            <?php elseif ($currentVisiblePassword === null): ?>
                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-base text-amber-800">這筆舊帳號目前沒有保存可見密碼，因為系統先前只保留雜湊值。請重新輸入一組密碼並儲存，帳號維護列表就會顯示。</div>
            <?php else: ?>
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="text-sm font-semibold text-slate-500">登入密碼</div>
                    <div class="mt-3 break-all font-mono text-lg font-semibold text-slate-900"><?= h($currentVisiblePassword) ?></div>
                </div>
            <?php endif; ?>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-panel">
            <h3 class="text-xl font-semibold text-slate-900">操作提醒</h3>
            <ul class="mt-4 space-y-3 text-base leading-7 text-slate-600">
                <li>新增帳號時，密碼長度至少需要 6 個字元。</li>
                <li>編輯帳號時若不輸入新密碼，系統會保留原本密碼。</li>
                <li>工程師帳號至少要保留一位，不能全部取消或刪除。</li>
            </ul>
        </section>
    </aside>
</div>
<?php renderAppLayoutEnd(); ?>