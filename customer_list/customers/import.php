<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/customer_fields.php';
require_once __DIR__ . '/../includes/customer_helpers.php';
require_once __DIR__ . '/../includes/spreadsheet_reader.php';
require_once __DIR__ . '/../includes/layout.php';

requireLogin();

$user = $_SESSION['user'];
$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['spreadsheet']) || !is_uploaded_file($_FILES['spreadsheet']['tmp_name'])) {
        $errors[] = '請先選擇要匯入的 Excel 或 CSV 檔案。';
    } else {
        try {
            $rows = readSpreadsheetRows($_FILES['spreadsheet']);

            if (count($rows) < 2) {
                throw new RuntimeException('檔案內容至少需要包含標題列與一筆資料。');
            }

            $headerMap = customerImportHeaderMap();
            $headerRow = $rows[0];
            $columnFieldMap = [];

            foreach ($headerRow as $index => $header) {
                $normalizedHeader = customerNormalizeHeader((string) $header);
                if (isset($headerMap[$normalizedHeader])) {
                    $columnFieldMap[$index] = $headerMap[$normalizedHeader];
                }
            }

            if (!in_array('customer_code', $columnFieldMap, true) || !in_array('customer_name', $columnFieldMap, true)) {
                throw new RuntimeException('匯入檔案缺少「客戶代號」與「客戶名稱」欄位。');
            }

            $inserted = 0;
            $updated = 0;
            $skipped = 0;
            $rowErrors = [];

            $pdo->beginTransaction();

            foreach (array_slice($rows, 1) as $rowIndex => $row) {
                $rawData = emptyCustomerData();

                foreach ($columnFieldMap as $columnIndex => $fieldName) {
                    $rawData[$fieldName] = $row[$columnIndex] ?? '';
                }

                $filledValues = array_filter($rawData, static fn($value): bool => trim((string) $value) !== '');
                if (!$filledValues) {
                    $skipped++;
                    continue;
                }

                [$preparedData, $validationErrors] = prepareCustomerData($rawData);
                if ($validationErrors) {
                    $rowErrors[] = '第 ' . ($rowIndex + 2) . ' 列：' . implode('、', $validationErrors);
                    continue;
                }

                $existingId = findCustomerIdByCode($pdo, (string) $preparedData['customer_code']);
                if ($existingId !== null) {
                    persistCustomer($pdo, $preparedData, (int) $user['id'], $existingId);
                    $updated++;
                } else {
                    persistCustomer($pdo, $preparedData, (int) $user['id']);
                    $inserted++;
                }
            }

            $pdo->commit();

            $result = [
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $rowErrors,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $e->getMessage();
        }
    }
}

renderAppLayoutStart('Excel 匯入 / 匯出', $user, 'import');
?>
<div class="grid gap-6">
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-panel lg:p-8 xl:p-10">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">Excel 匯入 / 匯出</h2>
            </div>
            <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                <a href="<?= h(appUrl('customers/export.php')) ?>" class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">Excel 匯出</a>
                <a href="<?= h(appUrl('customers/index.php')) ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">回客戶列表</a>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="mt-7 rounded-[1.75rem] border border-rose-200 bg-rose-50 px-5 py-4 text-base text-rose-700">
                <ul class="list-disc space-y-1 pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="mt-7 grid gap-4 md:grid-cols-3">
                <div class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-5">
                    <div class="text-sm font-semibold tracking-[0.08em] text-emerald-700">新增筆數</div>
                    <div class="mt-3 text-3xl font-bold text-emerald-800"><?= $result['inserted'] ?></div>
                </div>
                <div class="rounded-[1.5rem] border border-sky-200 bg-sky-50 p-5">
                    <div class="text-sm font-semibold tracking-[0.08em] text-sky-700">更新筆數</div>
                    <div class="mt-3 text-3xl font-bold text-sky-800"><?= $result['updated'] ?></div>
                </div>
                <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                    <div class="text-sm font-semibold tracking-[0.08em] text-slate-700">略過筆數</div>
                    <div class="mt-3 text-3xl font-bold text-slate-800"><?= $result['skipped'] ?></div>
                </div>
            </div>

            <?php if ($result['errors']): ?>
                <div class="mt-6 rounded-[1.75rem] border border-amber-200 bg-amber-50 px-5 py-4 text-base text-amber-700">
                    <div class="font-semibold">以下列資料未成功匯入：</div>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <?php foreach ($result['errors'] as $rowError): ?>
                            <li><?= h($rowError) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <section class="mt-8 rounded-[1.9rem] border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-orange-50 p-6 shadow-sm lg:p-8 xl:p-10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-800">匯入檔案</span>
                    <h3 class="mt-4 text-2xl font-bold text-slate-900">上傳檔案並更新客戶資料</h3>
                    <p class="mt-2 text-base leading-7 text-slate-600">系統會依照表頭名稱對應欄位；若客戶代號已存在，會直接更新原有資料。建議先確認表頭名稱與欄位順序正確。</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-white/80 px-4 py-3 text-sm font-medium text-amber-800 shadow-sm">
                    支援格式：`.xlsx`、`.csv`
                </div>
            </div>

            <form method="post" enctype="multipart/form-data" class="mt-7 space-y-6">
                <div class="rounded-[1.6rem] border-2 border-dashed border-amber-300 bg-white/90 p-5 lg:p-6">
                    <label for="spreadsheet" class="block text-base font-semibold text-slate-800">選擇檔案</label>
                    <p class="mt-2 text-sm leading-6 text-slate-500">請上傳你要匯入的 Excel 或 CSV 檔案，檔案名稱會顯示在右側，方便再次確認。</p>

                    <input id="spreadsheet" type="file" name="spreadsheet" accept=".xlsx,.csv" class="sr-only" required>

                    <div class="mt-5 flex flex-col gap-4 xl:flex-row xl:items-center">
                        <label for="spreadsheet" class="inline-flex cursor-pointer items-center justify-center rounded-2xl bg-amber-500 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-amber-200 transition hover:bg-amber-600">選擇檔案</label>
                        <div id="selected-file-name" class="flex-1 rounded-2xl border border-amber-100 bg-amber-50 px-5 py-4 text-base font-medium text-slate-600">尚未選擇任何檔案</div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4">
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-primary-600 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-primary-100 transition hover:bg-primary-700">開始匯入</button>
                    <a href="<?= h(appUrl('customers/export.php')) ?>" class="inline-flex items-center rounded-2xl bg-emerald-600 px-6 py-4 text-base font-semibold text-white shadow-lg shadow-emerald-100 transition hover:bg-emerald-700">匯出全部客戶資料</a>
                </div>
            </form>
        </section>

        <div class="mt-6 grid gap-5 xl:grid-cols-2">
            <section class="rounded-[1.75rem] border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-6 lg:p-7">
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-4 py-2 text-sm font-semibold text-emerald-700">匯出資料</span>
                <h3 class="mt-4 text-xl font-bold text-slate-900">快速匯出目前客戶資料</h3>
                <p class="mt-2 text-base leading-7 text-slate-600">若你只是想下載目前客戶資料，不需要重新選擇檔案，可以直接使用匯出功能取得 Excel 可開啟的 CSV。</p>
                <a href="<?= h(appUrl('customers/export.php')) ?>" class="mt-5 inline-flex items-center rounded-2xl border border-emerald-200 bg-white px-5 py-3 text-base font-semibold text-emerald-700 transition hover:bg-emerald-50">前往 Excel 匯出</a>
            </section>

            <section class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-6 lg:p-7">
                <span class="inline-flex items-center rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">匯入提醒</span>
                <ul class="mt-4 space-y-3 text-base leading-7 text-slate-600">
                    <li>1. 檔案第一列請放表頭名稱，並包含客戶代號與客戶名稱。</li>
                    <li>2. 若客戶代號已存在，系統會直接更新原資料，不會重複新增。</li>
                    <li>3. 若有格式錯誤，系統會列出失敗的列數與原因，方便你回頭修正。</li>
                </ul>
            </section>
        </div>
    </section>
</div>
<script>
    (function () {
        const fileInput = document.getElementById('spreadsheet');
        const fileName = document.getElementById('selected-file-name');

        if (!fileInput || !fileName) {
            return;
        }

        const updateFileName = () => {
            const selectedFile = fileInput.files && fileInput.files[0];
            fileName.textContent = selectedFile ? selectedFile.name : '尚未選擇任何檔案';
            fileName.classList.toggle('text-slate-900', Boolean(selectedFile));
            fileName.classList.toggle('font-semibold', Boolean(selectedFile));
        };

        fileInput.addEventListener('change', updateFileName);
        updateFileName();
    })();
</script>
<?php renderAppLayoutEnd(); ?>