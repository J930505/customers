<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/customer_fields.php';
require_once __DIR__ . '/../includes/customer_helpers.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();

$user = $_SESSION['user'];
$id = max(0, (int) ($_GET['id'] ?? 0));
$keyword = trim($_GET['keyword'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$status = $_GET['status'] ?? '';
$count = max(0, (int) ($_GET['count'] ?? 0));
$customer = fetchCustomerRecord($pdo, $id, true);

if (!$customer) {
    http_response_code(404);
    exit('找不到指定的客戶資料。');
}

$stmt = $pdo->prepare('SELECT customer_logs.*, users.username AS operated_by_name FROM customer_logs LEFT JOIN users ON users.id = customer_logs.operated_by WHERE customer_id = ? ORDER BY operated_at DESC, id DESC');
$stmt->execute([$id]);
$logs = $stmt->fetchAll();

$statusMessage = '';
if ($status === 'deleted_logs') {
    $statusMessage = $count > 0
        ? '已刪除 ' . $count . ' 筆編輯紀錄。'
        : '沒有可刪除的編輯紀錄。';
} elseif ($status === 'missing_logs') {
    $statusMessage = '請先勾選要刪除的編輯紀錄。';
}

function historyActionMeta(string $actionType): array
{
    return match ($actionType) {
        'update' => ['label' => '編輯', 'badge' => 'bg-sky-100 text-sky-700'],
        'create' => ['label' => '新增', 'badge' => 'bg-emerald-100 text-emerald-700'],
        'delete' => ['label' => '刪除', 'badge' => 'bg-rose-100 text-rose-700'],
        default => ['label' => $actionType, 'badge' => 'bg-slate-100 text-slate-700'],
    };
}

renderAppLayoutStart('客戶編輯紀錄', $user, 'customers');
?>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
    <section class="space-y-6">
        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-panel lg:p-7">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900"><?= h(customerDisplayValue('customer_name', $customer['customer_name'])) ?></h2>
                    <div class="mt-4 flex flex-wrap gap-3 text-base">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 font-medium text-slate-700">客戶代號：<?= h(customerDisplayValue('customer_code', $customer['customer_code'])) ?></span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 font-medium text-slate-700">通知日期：<?= h(customerDisplayValue('notify_date', $customer['notify_date'])) ?></span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-4 py-2 font-medium text-slate-700">紀錄 <?= count($logs) ?> 筆</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="<?= h(customerFormUrl($id, $keyword, $page)) ?>" class="inline-flex items-center rounded-2xl bg-primary-600 px-5 py-3 text-base font-semibold text-white transition hover:bg-primary-700">編輯資料</a>
                    <a href="<?= h(customerListUrl($keyword, $page)) ?>" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-base font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">回客戶列表</a>
                </div>
            </div>
        </div>

        <?php if ($statusMessage !== ''): ?>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-base font-medium text-emerald-700">
                <?= h($statusMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!$logs): ?>
            <div class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-panel">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="bi bi-clock-history text-2xl"></i></div>
                <h3 class="mt-5 text-2xl font-semibold text-slate-900">目前沒有編輯紀錄</h3>
                <p class="mt-3 text-base text-slate-500">只有在實際儲存且欄位內容有變動時，系統才會新增編輯紀錄。</p>
            </div>
        <?php else: ?>
            <form method="post" action="<?= h(appUrl('customers/history_delete.php')) ?>" class="space-y-5" onsubmit="return confirm('確定要刪除選取的編輯紀錄嗎？');">
                <input type="hidden" name="customer_id" value="<?= h($id) ?>">
                <input type="hidden" name="keyword" value="<?= h($keyword) ?>">
                <input type="hidden" name="page" value="<?= h($page) ?>">

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-[2rem] border border-slate-200 bg-white px-5 py-4 shadow-panel">
                    <label class="inline-flex items-center gap-3 text-base font-medium text-slate-700">
                        <input id="select-all-history" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        全選 / 取消全選
                    </label>
                    <button type="submit" class="inline-flex items-center rounded-2xl border border-rose-200 bg-white px-5 py-3 text-base font-semibold text-rose-600 transition hover:bg-rose-50">刪除所選紀錄</button>
                </div>

                <?php foreach ($logs as $log): ?>
                    <?php
                        $meta = historyActionMeta((string) $log['action_type']);
                        $oldData = decodeCustomerLogData($log['old_data'] ?? null);
                        $newData = decodeCustomerLogData($log['new_data'] ?? null);
                        $changes = customerLogChanges($oldData, $newData);
                    ?>
                    <article class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-panel lg:p-7">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-4">
                                <input type="checkbox" name="log_ids[]" value="<?= h($log['id']) ?>" class="history-checkbox mt-1 h-5 w-5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                                <div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold <?= h($meta['badge']) ?>"><?= h($meta['label']) ?></span>
                                        <span class="text-base font-medium text-slate-500"><?= h($log['operated_at']) ?></span>
                                    </div>
                                    <p class="mt-3 text-base text-slate-600">操作人：<?= h($log['operated_by_name'] ?: '系統') ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="mb-4 text-lg font-bold text-slate-900">本次儲存的欄位差異</div>
                            <?php if (!$changes): ?>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-base text-slate-500">這筆紀錄沒有可顯示的欄位差異。</div>
                            <?php else: ?>
                                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                                    <table class="min-w-full divide-y divide-slate-200 text-base">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <th class="px-5 py-4 text-left text-base font-semibold text-slate-600">欄位</th>
                                                <th class="px-5 py-4 text-left text-base font-semibold text-slate-600">修改前</th>
                                                <th class="px-5 py-4 text-left text-base font-semibold text-slate-600">修改後</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            <?php foreach ($changes as $change): ?>
                                                <tr>
                                                    <td class="px-5 py-4 text-lg font-semibold text-slate-900"><?= h($change['label']) ?></td>
                                                    <td class="px-5 py-4 whitespace-pre-line text-base leading-8 text-slate-600"><?= h($change['old']) ?></td>
                                                    <td class="px-5 py-4 whitespace-pre-line text-base leading-8 text-slate-900"><?= h($change['new']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </form>
        <?php endif; ?>
    </section>

    <aside class="space-y-6">
        <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-panel">
            <h3 class="text-xl font-semibold text-slate-900">紀錄說明</h3>
            <ul class="mt-4 space-y-3 text-base leading-7 text-slate-600">
                <li>只有實際儲存且內容有變動時，系統才會新增編輯紀錄。</li>
                <li>你可以勾選多筆紀錄後，一次批次刪除。</li>
                <li>Excel 匯入若更新既有客戶資料，也會留下編輯紀錄。</li>
            </ul>
        </div>
    </aside>
</div>
<script>
    (function () {
        const selectAll = document.getElementById('select-all-history');
        const checkboxes = document.querySelectorAll('.history-checkbox');

        if (!selectAll || checkboxes.length === 0) {
            return;
        }

        selectAll.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
        });
    })();
</script>
<?php renderAppLayoutEnd(); ?>