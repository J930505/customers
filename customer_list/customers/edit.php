<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/customer_fields.php';
require_once __DIR__ . '/../includes/customer_helpers.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();

$user = $_SESSION['user'];
$fields = customerFieldDefinitions();
$id = max(0, (int) ($_POST['id'] ?? $_GET['id'] ?? 0));
$keyword = trim($_POST['keyword'] ?? $_GET['keyword'] ?? '');
$page = max(1, (int) ($_POST['page'] ?? $_GET['page'] ?? 1));
$isCreate = $id === 0;
$errors = [];
$customer = emptyCustomerData();

if (!$isCreate) {
    $loadedCustomer = fetchCustomer($pdo, $id);

    if (!$loadedCustomer) {
        http_response_code(404);
        exit('找不到指定的客戶資料。');
    }

    $customer = array_merge($customer, $loadedCustomer);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$preparedData, $errors] = prepareCustomerData($_POST);

    if (!$errors && !empty($preparedData['customer_code'])) {
        $existingCustomerId = findCustomerIdByCode($pdo, (string) $preparedData['customer_code'], $isCreate ? null : $id);
        if ($existingCustomerId !== null) {
            $errors[] = '客戶代號已存在，請改用其他代號或編輯既有客戶。';
        }
    }

    if (!$errors) {
        persistCustomer($pdo, $preparedData, (int) $user['id'], $isCreate ? null : $id);
        $status = $isCreate ? 'created' : 'updated';

        header('Location: ' . customerListUrl($keyword, $page, ['status' => $status]));
        exit;
    }

    $customer = array_merge($customer, $_POST);
}

function customerFormSectionMap(): array
{
    return [
        'basic' => [
            'title' => '基本資料',
            'description' => '建立客戶辨識與通知資訊。',
            'container' => 'border-primary-100 bg-primary-50/60',
            'icon' => 'bi-person-vcard',
            'fields' => ['notify_date', 'billing_time', 'item_no', 'customer_code', 'customer_name'],
        ],
        'billing' => [
            'title' => '帳務與寄送',
            'description' => '整理帳務費、寄送方式與帳務處理欄位。',
            'container' => 'border-amber-100 bg-amber-50/70',
            'icon' => 'bi-receipt-cutoff',
            'fields' => ['monthly_fee', 'stationery', 'invoice', 'receipt_one', 'reply_mail', 'months_count', 'company_tax_id', 'tax_code', 'department', 'accountant', 'delivery_method', 'postal_code'],
        ],
        'notes' => [
            'title' => '地址與備註',
            'description' => '完整填寫聯絡地址與內部備註，方便後續查詢。',
            'container' => 'border-emerald-100 bg-emerald-50/70',
            'icon' => 'bi-journal-text',
            'fields' => ['contact_address', 'notes', 'billing_detail_zhongshan'],
        ],
    ];
}

function customerFieldGridClass(string $fieldName): string
{
    return match ($fieldName) {
        'customer_name', 'contact_address', 'notes', 'billing_detail_zhongshan' => 'md:col-span-2 xl:col-span-4',
        'billing_time', 'monthly_fee', 'company_tax_id', 'department', 'accountant', 'delivery_method' => 'xl:col-span-2',
        default => 'xl:col-span-1',
    };
}

function customerInputClass(array $field): string
{
    $base = 'block w-full rounded-2xl border border-slate-200 bg-white px-5 py-4 text-base text-slate-900 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100';

    if (($field['input'] ?? '') === 'textarea') {
        return $base . ' min-h-[132px] leading-7';
    }

    return $base;
}

$fieldMap = customerFieldMap();
$sections = customerFormSectionMap();
$pageTitle = $isCreate ? '新增客戶' : '編輯客戶';
$pageDescription = $isCreate
    ? '請依欄位分區填寫資料，完成後會直接回到客戶列表。'
    : '你可以在這裡調整客戶資料，儲存後會同步更新客戶列表。';
$submitLabel = $isCreate ? '建立客戶資料' : '儲存變更';

renderAppLayoutStart($pageTitle, $user, $isCreate ? 'customer-create' : 'customers');
?>
<div class="grid gap-6">
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-panel lg:p-8 xl:p-10">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900"><?= h($pageTitle) ?></h2>
                <p class="mt-3 text-base leading-7 text-slate-500"><?= h($pageDescription) ?></p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="<?= h(customerListUrl($keyword, $page)) ?>" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">回客戶列表</a>
                <?php if (!$isCreate): ?>
                    <form method="post" action="<?= h(appUrl('customers/delete.php')) ?>" onsubmit="return confirm('確定要刪除這筆客戶資料嗎？');">
                        <input type="hidden" name="id" value="<?= h($id) ?>">
                        <input type="hidden" name="keyword" value="<?= h($keyword) ?>">
                        <input type="hidden" name="page" value="<?= h($page) ?>">
                        <button type="submit" class="inline-flex items-center rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-base font-semibold text-rose-600 transition hover:bg-rose-100">刪除客戶</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="mt-7 rounded-[1.75rem] border border-rose-200 bg-rose-50 px-5 py-4 text-base text-rose-700">
                <div class="mb-2 font-semibold">請先修正以下欄位：</div>
                <ul class="list-disc space-y-1 pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="mt-8 space-y-8">
            <input type="hidden" name="id" value="<?= h($id) ?>">
            <input type="hidden" name="keyword" value="<?= h($keyword) ?>">
            <input type="hidden" name="page" value="<?= h($page) ?>">

            <?php foreach ($sections as $section): ?>
                <section class="rounded-[1.75rem] border p-6 shadow-sm lg:p-7 <?= h($section['container']) ?>">
                    <div class="flex flex-col gap-3 border-b border-white/70 pb-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-700 shadow-sm">
                                <i class="bi <?= h($section['icon']) ?> text-xl"></i>
                            </span>
                            <div>
                                <h3 class="text-xl font-bold text-slate-900"><?= h($section['title']) ?></h3>
                                <p class="mt-1 text-sm leading-6 text-slate-600"><?= h($section['description']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <?php foreach ($section['fields'] as $fieldName): ?>
                            <?php $field = $fieldMap[$fieldName]; ?>
                            <?php $value = customerFormValue($fieldName, $customer[$fieldName] ?? ''); ?>
                            <div class="<?= h(customerFieldGridClass($fieldName)) ?>">
                                <label for="<?= h($fieldName) ?>" class="mb-2 block text-sm font-semibold tracking-[0.04em] text-slate-700"><?= h($field['label']) ?></label>
                                <?php if (($field['input'] ?? '') === 'textarea'): ?>
                                    <textarea id="<?= h($fieldName) ?>" name="<?= h($fieldName) ?>" rows="<?= h($field['rows'] ?? '3') ?>" class="<?= h(customerInputClass($field)) ?>"><?= h($value) ?></textarea>
                                <?php else: ?>
                                    <input id="<?= h($fieldName) ?>" type="<?= h($field['input']) ?>" name="<?= h($fieldName) ?>" value="<?= h($value) ?>" class="<?= h(customerInputClass($field)) ?>"<?php if (!empty($field['placeholder'])): ?> placeholder="<?= h($field['placeholder']) ?>"<?php endif; ?><?php if (!empty($field['min'])): ?> min="<?= h($field['min']) ?>"<?php endif; ?><?php if (!empty($field['step'])): ?> step="<?= h($field['step']) ?>"<?php endif; ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="flex flex-wrap items-center justify-between gap-4 rounded-[1.75rem] border border-slate-200 bg-slate-50 px-6 py-5">
                <div>
                    <div class="text-lg font-semibold text-slate-900">確認資料後即可送出</div>
                    <div class="mt-1 text-sm text-slate-500">儲存後會直接回到客戶列表，並保留你目前的搜尋與頁碼位置。</div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="<?= h(customerListUrl($keyword, $page)) ?>" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">取消返回</a>
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-primary-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-primary-100 transition hover:bg-primary-700"><?= h($submitLabel) ?></button>
                </div>
            </div>
        </form>
    </section>
</div>
<?php renderAppLayoutEnd(); ?>