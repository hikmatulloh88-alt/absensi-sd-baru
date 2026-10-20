<?php
declare(strict_types=1);
require_once __DIR__ . '/../modules/spj_bosp/lib_spj_bku.php';
require_once __DIR__ . '/../modules/spj_bosp/lib_spj_rkas.php';

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

$classificationCases = [
    ['Saldo awal bulan', '', 0, 1000, 'SALDO'],
    ['Terima PPh Pasal 21', '', 0, 500, 'PENERIMAAN_PAJAK'],
    ['Setor PPN', '', 500, 0, 'SETOR_PAJAK'],
    ['Pembelian alat tulis', '', 250, 0, 'PENGELUARAN'],
    ['Transfer dana masuk', '', 0, 2000, 'PENERIMAAN'],
    ['Catatan administrasi', '', 0, 0, 'LAINNYA'],
];
foreach ($classificationCases as [$description, $activity, $out, $in, $expected]) {
    $actual = bku_classify($description, $activity, (float) $out, (float) $in);
    if ($actual !== $expected) {
        fwrite(STDERR, 'FAIL bku_classify(' . $description . '): got ' . $actual . ', expected ' . $expected . PHP_EOL);
        exit(1);
    }
}
echo 'BKU classification tests passed: ' . count($classificationCases) . ' cases.' . PHP_EOL;

$rkasCases = [
    ['Rp 1.234.567', 1234567.0],
    ['1,234,567', 1234567.0],
    ['1.234,56', 1234.56],
    ['1,234.56', 1234.56],
    ['123,45', 123.45],
    ['', null],
    ['-100', null],
    ['invalid', null],
    ['1e9999', null],
];
foreach ($rkasCases as [$input, $expected]) {
    $actual = rkas_money($input);
    if ($expected === null ? $actual !== null : $actual === null || abs($actual - $expected) > 0.00001) {
        fwrite(STDERR, 'FAIL rkas_money(' . var_export($input, true) . '): got ' . var_export($actual, true) . ', expected ' . var_export($expected, true) . PHP_EOL);
        exit(1);
    }
}
if (rkas_norm("\xEF\xBB\xBF Kode Rekening") !== 'koderekening') {
    fwrite(STDERR, "FAIL rkas_norm strips BOM and punctuation" . PHP_EOL);
    exit(1);
}
echo 'RKAS logic tests passed: ' . count($rkasCases) . " money cases + header normalization." . PHP_EOL;
