<?php

function readSpreadsheetRows(array $uploadedFile): array
{
    $fileName = strtolower((string) ($uploadedFile['name'] ?? ''));
    $tmpName = (string) ($uploadedFile['tmp_name'] ?? '');

    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('找不到上傳的檔案。');
    }

    if (str_ends_with($fileName, '.csv')) {
        return readCsvRows($tmpName);
    }

    if (str_ends_with($fileName, '.xlsx')) {
        return readXlsxRows($tmpName);
    }

    throw new RuntimeException('只支援 .xlsx 或 .csv 檔案。');
}

function readCsvRows(string $path): array
{
    $handle = fopen($path, 'rb');

    if ($handle === false) {
        throw new RuntimeException('無法讀取 CSV 檔案。');
    }

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        if (!$rows && isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }

        $rows[] = array_map(static fn($value) => trim((string) $value), $row);
    }

    fclose($handle);

    return $rows;
}

function readXlsxRows(string $path): array
{
    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new RuntimeException('無法開啟 Excel 檔案。');
    }

    $sharedStrings = readXlsxSharedStrings($zip);
    $sheetPath = resolveFirstWorksheetPath($zip);
    $sheetXml = $zip->getFromName($sheetPath);

    if ($sheetXml === false) {
        $zip->close();
        throw new RuntimeException('讀取工作表內容失敗。');
    }

    $sheet = simplexml_load_string($sheetXml);

    if ($sheet === false || !isset($sheet->sheetData)) {
        $zip->close();
        throw new RuntimeException('Excel 內容格式不正確。');
    }

    $rows = [];

    foreach ($sheet->sheetData->row as $row) {
        $rowValues = [];

        foreach ($row->c as $cell) {
            $reference = (string) ($cell['r'] ?? '');
            $columnIndex = xlsxColumnIndex($reference);
            $rowValues[$columnIndex] = xlsxCellValue($cell, $sharedStrings);
        }

        if (!$rowValues) {
            continue;
        }

        ksort($rowValues);
        $maxIndex = max(array_keys($rowValues));
        $normalizedRow = [];

        for ($i = 0; $i <= $maxIndex; $i++) {
            $normalizedRow[] = trim((string) ($rowValues[$i] ?? ''));
        }

        $rows[] = $normalizedRow;
    }

    $zip->close();

    return $rows;
}

function readXlsxSharedStrings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');

    if ($xml === false) {
        return [];
    }

    $shared = simplexml_load_string($xml);
    $strings = [];

    foreach ($shared->si as $item) {
        if (isset($item->t)) {
            $strings[] = (string) $item->t;
            continue;
        }

        $text = '';
        foreach ($item->r as $run) {
            $text .= (string) $run->t;
        }
        $strings[] = $text;
    }

    return $strings;
}

function resolveFirstWorksheetPath(ZipArchive $zip): string
{
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

    if ($workbookXml === false || $relsXml === false) {
        throw new RuntimeException('Excel 檔案缺少必要結構。');
    }

    $workbook = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);

    $sheet = $workbook->sheets->sheet[0] ?? null;
    if ($sheet === null) {
        throw new RuntimeException('Excel 檔案中沒有工作表。');
    }

    $relationId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;

    foreach ($rels->Relationship as $relation) {
        if ((string) $relation['Id'] === $relationId) {
            return 'xl/' . ltrim((string) $relation['Target'], '/');
        }
    }

    throw new RuntimeException('找不到對應的工作表檔案。');
}

function xlsxColumnIndex(string $reference): int
{
    preg_match('/^[A-Z]+/', strtoupper($reference), $matches);
    $letters = $matches[0] ?? 'A';
    $index = 0;

    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }

    return $index - 1;
}

function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
{
    $type = (string) ($cell['t'] ?? '');

    if ($type === 'inlineStr') {
        return (string) ($cell->is->t ?? '');
    }

    if ($type === 's') {
        $sharedIndex = (int) ($cell->v ?? 0);
        return (string) ($sharedStrings[$sharedIndex] ?? '');
    }

    return (string) ($cell->v ?? '');
}