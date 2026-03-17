<?php

function customerFieldDefinitions(): array
{
    return [
        ['name' => 'notify_date', 'label' => "\u{901A}\u{77E5}\u{65E5}\u{671F}", 'input' => 'date'],
        ['name' => 'billing_time', 'label' => "\u{958B}\u{55AE}\u{6642}\u{9593}", 'input' => 'text', 'placeholder' => "\u{4F8B}\u{5982} 09:30"],
        ['name' => 'item_no', 'label' => "\u{9805}\u{6B21}", 'input' => 'number', 'step' => '1', 'min' => '0'],
        ['name' => 'customer_code', 'label' => "\u{5BA2}\u{6236}\u{4EE3}\u{865F}", 'input' => 'text'],
        ['name' => 'customer_name', 'label' => "\u{5BA2}\u{6236}\u{540D}\u{7A31}", 'input' => 'text'],
        ['name' => 'monthly_fee', 'label' => "\u{5E33}\u{52D9}\u{8CBB}\u{FF0F}\u{6708}", 'input' => 'number', 'step' => '1', 'min' => '0'],
        ['name' => 'stationery', 'label' => "\u{6587}\u{5177}\u{7528}\u{54C1}", 'input' => 'text'],
        ['name' => 'invoice', 'label' => "\u{767C}\u{7968}", 'input' => 'text'],
        ['name' => 'receipt_one', 'label' => "\u{6536}\u{64DA}", 'input' => 'text'],
        ['name' => 'reply_mail', 'label' => "\u{56DE}\u{90F5}", 'input' => 'text'],
        ['name' => 'months_count', 'label' => "\u{5E7E}\u{500B}\u{6708}", 'input' => 'number', 'step' => '1', 'min' => '0'],
        ['name' => 'company_tax_id', 'label' => "\u{7D71}\u{4E00}\u{7DE8}\u{865F}", 'input' => 'text'],
        ['name' => 'tax_code', 'label' => "\u{7A05}\u{7DE8}", 'input' => 'text'],
        ['name' => 'department', 'label' => "\u{90E8}\u{9580}", 'input' => 'text'],
        ['name' => 'accountant', 'label' => "\u{5E33}\u{52D9}\u{54E1}", 'input' => 'text'],
        ['name' => 'notes', 'label' => "\u{5099}\u{8A3B}", 'input' => 'textarea', 'rows' => '3'],
        ['name' => 'billing_detail_zhongshan', 'label' => "\u{8ACB}\u{6B3E}\u{660E}\u{7D30}\u{4E2D}\u{5C71}\u{7248}\u{672C}", 'input' => 'textarea', 'rows' => '3'],
        ['name' => 'delivery_method', 'label' => "\u{5BC4}\u{9001}\u{65B9}\u{5F0F}", 'input' => 'text'],
        ['name' => 'contact_address', 'label' => "\u{806F}\u{7D61}\u{5730}\u{5740}", 'input' => 'textarea', 'rows' => '4'],
        ['name' => 'postal_code', 'label' => "\u{90F5}\u{905E}\u{5340}\u{865F}", 'input' => 'text'],
    ];
}

function customerFieldMap(): array
{
    $map = [];

    foreach (customerFieldDefinitions() as $field) {
        $map[$field['name']] = $field;
    }

    return $map;
}

function customerFieldNames(): array
{
    return array_map(static fn(array $field): string => $field['name'], customerFieldDefinitions());
}

function customerSelectColumns(): string
{
    return implode(', ', customerFieldNames());
}

function customerSummaryFieldNames(): array
{
    return ['customer_code', 'customer_name', 'notify_date', 'accountant', 'contact_address'];
}

function customerNormalizeHeader(string $header): string
{
    $header = preg_replace('/\s+/u', '', trim($header));

    return strtolower((string) $header);
}

function customerImportHeaderMap(): array
{
    $map = [];

    foreach (customerFieldDefinitions() as $field) {
        $map[customerNormalizeHeader($field['label'])] = $field['name'];
        $map[customerNormalizeHeader($field['name'])] = $field['name'];
    }

    $map[customerNormalizeHeader("\u{5E33}\u{52D9}\u{8CBB}/\u{6708}")] = 'monthly_fee';
    $map[customerNormalizeHeader("\u{5E33}\u{52D9}\u{8CBB}\u{FF0F}\u{6708}")] = 'monthly_fee';
    $map[customerNormalizeHeader("\u{6536}\u{64DA}")] = 'receipt_one';
    $map[customerNormalizeHeader("\u{6536}\u{64DA}1")] = 'receipt_one';
    $map[customerNormalizeHeader("\u{6536}\u{64DA}\u{4E00}")] = 'receipt_one';
    $map[customerNormalizeHeader("\u{6536}\u{64DA}2")] = 'receipt_one';
    $map[customerNormalizeHeader("\u{6536}\u{64DA}\u{4E8C}")] = 'receipt_one';
    $map[customerNormalizeHeader("\u{7D71}\u{7DE8}")] = 'company_tax_id';
    $map[customerNormalizeHeader("\u{516C}\u{53F8}\u{7D71}\u{7DE8}")] = 'company_tax_id';
    $map[customerNormalizeHeader("\u{8ACB}\u{6B3E}\u{660E}\u{7D30}\u{4E2D}\u{5C71}")] = 'billing_detail_zhongshan';

    return $map;
}
