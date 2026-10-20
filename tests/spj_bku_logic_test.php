<?php
declare(strict_types=1);
require_once __DIR__ . '/../modules/spj_bosp/lib_spj_bku.php';

$cases = [
    ['Rp 1.234.567', 1234567.0],
    ['1,234,567', 1234567.0],
    ['1.234,56', 1234.56],
    ['1,234.56', 1234.56],
    ['123,45', 123.45],
    ['0', 0.0],
    ['', 0.0],
    ['-25', null],
    ['not-a-number', null],
    ['1e9999', null],
];
foreach ($cases as [$input, $expected]) {
    $actual = bku_money($input);
    if ($expected === null ? $actual !== null : $actual === null || abs($actual - $expected) > 0.00001) {
        fwrite(STDERR, 'FAIL bku_money(' . var_export($input, true) . '): got ' . var_export($actual, true) . ', expected ' . var_export($expected, true) . PHP_EOL);
        exit(1);
    }
}
if (bku_norm("\xEF\xBB\xBF Nomor Bukti") !== 'nomorbukti') {
    fwrite(STDERR, "FAIL bku_norm strips BOM and punctuation" . PHP_EOL);
    exit(1);
}
echo 'BKU logic tests passed: ' . count($cases) . " money cases + header normalization." . PHP_EOL;
