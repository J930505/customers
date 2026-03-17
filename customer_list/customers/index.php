<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/customer_fields.php';
require_once __DIR__ . '/../includes/customer_helpers.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();

$user = $_SESSION['user'];
$fields = customerFieldDefinitions();
$keyword = trim($_GET['keyword'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$status = $_GET['status'] ?? '';
$count = max(0, (int) ($_GET['count'] ?? 0));

$where = ' WHERE is_deleted = 0 ';
$params = [];

if ($keyword !== '') {
    $normalizedKeyword = trim(normalizeCustomerWideText($keyword));
    $searchKeyword = $normalizedKeyword !== '' ? $normalizedKeyword : $keyword;
    $searchableFields = customerFieldNames();
    $searchClauses = [];

    foreach ($searchableFields as $fieldName) {
        $searchClauses[] = 'CAST(' . $fieldName . ' AS CHAR) LIKE ?';
        $params[] = '%' . $searchKeyword . '%';
    }

    $digitKeyword = preg_replace('/\D+/u', '', $searchKeyword) ?? '';

    if ($digitKeyword !== '') {
        $digitVariants = array_values(array_unique([
            $digitKeyword,
            ltrim($digitKeyword, '0') === '' ? '0' : ltrim($digitKeyword, '0'),
        ]));

        foreach ($searchableFields as $fieldName) {
            $compactField = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CAST(" . $fieldName . " AS CHAR), ',', ''), '.', ''), '-', ''), '/', ''), ' ', ''), '?', '')";

            foreach ($digitVariants as $digitVariant) {
                $searchClauses[] = $compactField . ' LIKE ?';
                $params[] = '%' . $digitVariant . '%';
            }
        }
    }

    $where .= ' AND (' . implode(' OR ', $searchClauses) . ') ';
}

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM customers {$where}");
$stmt->execute($params);
$total = (int) $stmt->fetch()['total'];
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT id, " . customerSelectColumns() . " FROM customers {$where} ORDER BY item_no ASC, id ASC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$displayStart = $total === 0 ? 0 : $offset + 1;
$displayEnd = $total === 0 ? 0 : $offset + count($rows);
$statusMessages = [
    'created' => '客戶資料已建立。',
    'updated' => '客戶資料已更新。',
    'deleted' => '客戶資料已刪除。',
    'imported' => 'Excel 匯入完成。',
    'missing' => '找不到指定的客戶資料。',
    'missing_selection' => '請先勾選要刪除的客戶資料。',
    'missing_export_selection' => '請先勾選要匯出的客戶資料。',
];
$statusMessage = $statusMessages[$status] ?? '';

if ($status === 'batch_deleted') {
    $statusMessage = $count > 0
        ? '批次刪除完成，已刪除 ' . $count . ' 筆資料。'
        : '請先勾選要刪除的客戶資料。';
}

function customerDetailCardClass(string $fieldName): string
{
    return in_array($fieldName, ['contact_address', 'notes', 'billing_detail_zhongshan'], true)
        ? 'md:col-span-2 xl:col-span-3'
        : '';
}

renderAppLayoutStart('客戶列表', $user, 'customers');
?>
<div class="grid gap-6">
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-panel lg:p-7">
        <div class="flex flex-col gap-4 2xl:flex-row 2xl:items-center 2xl:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">客戶列表</h2>
                    <span class="inline-flex items-center rounded-full bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700">共 <?= $total ?> 筆</span>
                </div>
                <p class="mt-3 text-base leading-7 text-slate-500">支援搜尋所有欄位、勾選匯出與批次刪除。</p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <a href="<?= h(appUrl('customers/edit.php')) ?>" class="inline-flex items-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary-100 transition hover:bg-primary-700">新增客戶</a>
                <a href="<?= h(appUrl('customers/export.php')) ?>" class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">匯出全部 Excel</a>
            </div>
        </div>

        <form method="get" class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1fr)_170px_170px] xl:items-end">
            <div>
                <label for="keyword" class="mb-3 block text-base font-semibold text-slate-700">搜尋關鍵字</label>
                <input id="keyword" type="text" name="keyword" value="<?= h($keyword) ?>" placeholder="可搜尋客戶所有欄位，例如名稱、地址、帳務費、備註" class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-base text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            </div>
            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-primary-600 px-4 py-4 text-base font-semibold text-white transition hover:bg-primary-700">搜尋</button>
            <a href="<?= h(appUrl('customers/index.php')) ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-4 text-base font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">清除搜尋</a>
        </form>

        <div class="mt-5 flex flex-wrap items-center gap-3 text-base text-slate-500">
            <span>目前顯示第 <?= $displayStart ?> 到 <?= $displayEnd ?> 筆，共 <?= $total ?> 筆資料</span>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-500">每頁 10 筆</span>
        </div>
    </section>

    <?php if ($statusMessage !== ''): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-base font-medium text-emerald-700">
            <?= h($statusMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!$rows): ?>
        <section class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-panel">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="bi bi-search text-2xl"></i></div>
            <h3 class="mt-5 text-2xl font-semibold text-slate-900">目前沒有符合條件的客戶資料</h3>
            <p class="mt-3 text-base text-slate-500">可以調整搜尋條件，或直接新增客戶資料。</p>
        </section>
    <?php else: ?>
        <form method="post" action="<?= h(appUrl('customers/delete.php')) ?>" class="space-y-5">
            <input type="hidden" name="keyword" value="<?= h($keyword) ?>">
            <input type="hidden" name="page" value="<?= h((string) $page) ?>">

            <section class="flex flex-wrap items-center justify-between gap-3 rounded-[2rem] border border-slate-200 bg-white px-5 py-4 shadow-panel">
                <label class="inline-flex cursor-pointer items-center gap-3 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-base font-medium text-slate-700 transition hover:border-primary-300 hover:bg-primary-50">
                    <input id="select-all-customers" type="checkbox" class="peer sr-only">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 bg-white text-transparent transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-checked:text-white">
                        <i class="bi bi-check-lg text-sm"></i>
                    </span>
                    全選 / 取消全選
                </label>
                <div class="flex flex-wrap items-center gap-3">
                    <span id="selected-customers-count" class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600">已選 0 筆</span>
                    <button id="export-selected-customers" type="submit" formaction="<?= h(appUrl('customers/export.php')) ?>" formmethod="post" class="inline-flex items-center rounded-2xl border border-emerald-200 bg-white px-5 py-3 text-base font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400" disabled>匯出所選 Excel</button>
                    <button id="delete-selected-customers" type="submit" class="inline-flex items-center rounded-2xl border border-rose-200 bg-white px-5 py-3 text-base font-semibold text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400" disabled onclick="return confirm('確定要刪除選取的客戶資料嗎？');">批次刪除</button>
                </div>
            </section>

            <section class="grid gap-5">
                <?php foreach ($rows as $row): ?>
                    <div class="relative pl-8 sm:pl-10">
                        <label class="absolute left-0 top-1/2 z-10 flex h-14 w-14 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white shadow-panel ring-8 ring-slate-100 transition hover:border-primary-300 hover:bg-primary-50">
                            <input type="checkbox" name="ids[]" value="<?= h((string) $row['id']) ?>" class="customer-select peer sr-only">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 bg-white text-transparent transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-checked:text-white">
                                <i class="bi bi-check-lg text-sm"></i>
                            </span>
                        </label>

                        <details class="customer-disclosure rounded-[2rem] border border-slate-200 bg-white shadow-panel transition hover:border-slate-300">
                            <summary class="cursor-pointer px-6 py-6 sm:px-7">
                                <div class="flex items-start gap-4">
                                    <span class="customer-chevron mt-1 text-slate-400"><i class="bi bi-chevron-right text-lg"></i></span>
                                    <div class="grid flex-1 gap-5 md:grid-cols-2 xl:grid-cols-8">
                                        <div>
                                            <div class="text-sm font-semibold tracking-[0.12em] text-slate-400">客戶代號</div>
                                            <div class="mt-3 text-xl font-semibold text-slate-900"><?= h(customerDisplayValue('customer_code', $row['customer_code'])) ?></div>
                                        </div>
                                        <div class="xl:col-span-2">
                                            <div class="text-sm font-semibold tracking-[0.12em] text-slate-400">客戶名稱</div>
                                            <div class="mt-3 break-words text-lg font-semibold leading-8 text-slate-900 xl:text-xl"><?= h(customerDisplayValue('customer_name', $row['customer_name'])) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold tracking-[0.12em] text-slate-400">通知日期</div>
                                            <div class="mt-3 text-xl font-semibold text-slate-900"><?= h(customerDisplayValue('notify_date', $row['notify_date'])) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold tracking-[0.12em] text-slate-400">帳務員</div>
                                            <div class="mt-3 text-xl font-semibold text-slate-900"><?= h(customerDisplayValue('accountant', $row['accountant'])) ?></div>
                                        </div>
                                        <div class="xl:col-span-3">
                                            <div class="text-sm font-semibold tracking-[0.12em] text-slate-400">聯絡地址</div>
                                            <div class="mt-3 break-words text-lg font-semibold leading-8 text-slate-900 xl:text-xl"><?= h(customerDisplayValue('contact_address', $row['contact_address'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </summary>
                            <div class="border-t border-slate-200 px-6 py-6 sm:px-7">
                                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    <?php foreach ($fields as $field): ?>
                                        <?php $detailCardClass = customerDetailCardClass($field['name']); ?>
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 <?= $detailCardClass ?>">
                                            <div class="text-sm font-semibold tracking-[0.12em] text-slate-400"><?= h($field['label']) ?></div>
                                            <div class="mt-3 whitespace-pre-line break-words text-base font-medium leading-7 text-slate-900"><?= h(customerDisplayValue($field['name'], $row[$field['name']] ?? null)) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-6 flex flex-wrap gap-3 border-t border-slate-100 pt-5">
                                    <a href="<?= h(customerFormUrl((int) $row['id'], $keyword, $page)) ?>" class="inline-flex items-center rounded-2xl bg-primary-600 px-5 py-3 text-base font-semibold text-white transition hover:bg-primary-700">編輯</a>
                                    <a href="<?= h(customerHistoryUrl((int) $row['id'], $keyword, $page)) ?>" class="inline-flex items-center rounded-2xl border border-sky-200 bg-white px-5 py-3 text-base font-semibold text-sky-700 transition hover:bg-sky-50">編輯紀錄</a>
                                    <button type="submit" name="id" value="<?= h((string) $row['id']) ?>" formaction="<?= h(appUrl('customers/delete.php')) ?>" formmethod="post" class="inline-flex items-center rounded-2xl border border-rose-200 bg-white px-5 py-3 text-base font-semibold text-rose-600 transition hover:bg-rose-50" onclick="return prepareSingleCustomerDelete();">刪除</button>
                                </div>
                            </div>
                        </details>
                    </div>
                <?php endforeach; ?>
            </section>
        </form>
    <?php endif; ?>

    <nav class="flex justify-center">
        <ul class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-3 shadow-panel">
            <li>
                <a href="<?= $page <= 1 ? '#' : h(customerListUrl($keyword, $page - 1)) ?>" class="inline-flex items-center rounded-full px-4 py-2 text-base font-medium <?= $page <= 1 ? 'pointer-events-none text-slate-300' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">上一頁</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li>
                    <a href="<?= h(customerListUrl($keyword, $i)) ?>" class="inline-flex h-11 w-11 items-center justify-center rounded-full text-base font-semibold <?= $i === $page ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li>
                <a href="<?= $page >= $totalPages ? '#' : h(customerListUrl($keyword, $page + 1)) ?>" class="inline-flex items-center rounded-full px-4 py-2 text-base font-medium <?= $page >= $totalPages ? 'pointer-events-none text-slate-300' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">下一頁</a>
            </li>
        </ul>
    </nav>
</div>
<script>
    (function () {
        const selectAll = document.getElementById('select-all-customers');
        const checkboxes = Array.from(document.querySelectorAll('.customer-select'));
        const counter = document.getElementById('selected-customers-count');
        const exportButton = document.getElementById('export-selected-customers');
        const deleteButton = document.getElementById('delete-selected-customers');

        if (!selectAll || checkboxes.length === 0 || !counter || !exportButton || !deleteButton) {
            return;
        }

        const updateCounter = () => {
            const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
            const hasSelection = checkedCount > 0;

            counter.textContent = '已選 ' + checkedCount + ' 筆';
            selectAll.checked = checkedCount === checkboxes.length;
            exportButton.disabled = !hasSelection;
            deleteButton.disabled = !hasSelection;
        };

        selectAll.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            updateCounter();
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateCounter);
        });

        window.prepareSingleCustomerDelete = function () {
            const confirmed = window.confirm('確定要刪除這筆客戶資料嗎？');

            if (!confirmed) {
                return false;
            }

            checkboxes.forEach((checkbox) => {
                checkbox.disabled = true;
            });

            return true;
        };

        updateCounter();
    })();
</script>
<?php renderAppLayoutEnd(); ?>
