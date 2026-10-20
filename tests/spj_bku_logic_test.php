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

$duplicateRow = ['tanggal'=>'2026-02-10','nomor'=>'BKU-001','uraian'=>'Terima PPh Pasal 21','jenis'=>'PENERIMAAN_PAJAK','masuk'=>500.0,'keluar'=>0.0];
if (bku_row_key('BOSP', $duplicateRow) !== bku_row_key('BOSP', $duplicateRow)) {
    fwrite(STDERR, "FAIL identical BKU rows must have identical fingerprints" . PHP_EOL);
    exit(1);
}
$differentKind = $duplicateRow;
$differentKind['jenis'] = 'PENGELUARAN';
if (bku_row_key('BOSP', $duplicateRow) === bku_row_key('BOSP', $differentKind)) {
    fwrite(STDERR, "FAIL different BKU categories must have different fingerprints" . PHP_EOL);
    exit(1);
}
$differentSource = bku_row_key('BOS_KINERJA', $duplicateRow);
if (bku_row_key('BOSP', $duplicateRow) === $differentSource) {
    fwrite(STDERR, "FAIL different funding sources must have different fingerprints" . PHP_EOL);
    exit(1);
}
echo "BKU duplicate fingerprint tests passed: 3 checks." . PHP_EOL;

$delta = bku_balance_delta(1000.0, 250.0, 100.0, 1150.0);
if (abs($delta) > 0.001) {
    fwrite(STDERR, 'FAIL expected zero balance delta, got ' . $delta . PHP_EOL);
    exit(1);
}
$delta = bku_balance_delta(1000.0, 250.0, 100.0, 1140.0);
if (abs($delta - (-10.0)) > 0.001) {
    fwrite(STDERR, 'FAIL expected -10 balance delta, got ' . $delta . PHP_EOL);
    exit(1);
}
echo "BKU balance delta tests passed: 2 checks." . PHP_EOL;

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

$reconcileRows = [
    ['jenis'=>'SALDO','masuk'=>0.0,'keluar'=>0.0,'saldo'=>1000.0,'selisih_saldo'=>null],
    ['jenis'=>'PENGELUARAN','masuk'=>0.0,'keluar'=>100.0,'saldo'=>null,'selisih_saldo'=>null],
    ['jenis'=>'PENERIMAAN','masuk'=>50.0,'keluar'=>0.0,'saldo'=>null,'selisih_saldo'=>null],
    ['jenis'=>'PENGELUARAN','masuk'=>0.0,'keluar'=>25.0,'saldo'=>925.0,'selisih_saldo'=>null],
];
[$reconciled, $checks, $warnings] = bku_reconcile_balances($reconcileRows);
if ($checks !== 1 || $warnings !== 0 || abs((float)$reconciled[3]['selisih_saldo']) > 0.001) {
    fwrite(STDERR, "FAIL reconciliation must accumulate movements across blank saldo rows.\n");
    exit(1);
}
$reconcileRows[3]['saldo'] = 920.0;
[$reconciled, $checks, $warnings] = bku_reconcile_balances($reconcileRows);
if ($checks !== 1 || $warnings !== 1 || abs((float)$reconciled[3]['selisih_saldo'] - (-5.0)) > 0.001) {
    fwrite(STDERR, "FAIL reconciliation must flag discrepancy after blank saldo rows.\n");
    exit(1);
}
echo "BKU reconciliation tests passed: blank-saldo movement accumulation + discrepancy detection.\n";

$reconcileRows = [
    ['jenis'=>'SALDO','masuk'=>0.0,'keluar'=>0.0,'saldo'=>1000.0,'selisih_saldo'=>null],
    ['jenis'=>'SALDO','masuk'=>0.0,'keluar'=>0.0,'saldo'=>1000.0,'selisih_saldo'=>null],
    ['jenis'=>'PENGELUARAN','masuk'=>0.0,'keluar'=>100.0,'saldo'=>900.0,'selisih_saldo'=>null],
];
[$reconciled, $checks, $warnings] = bku_reconcile_balances($reconcileRows);
if ($checks !== 1 || $warnings !== 0 || abs((float)$reconciled[1]['selisih_saldo'] ?? 0.0) > 0.001 || abs((float)$reconciled[2]['selisih_saldo']) > 0.001) {
    fwrite(STDERR, "FAIL opening saldo rows must reset the reconciliation anchor without a false warning.\n");
    exit(1);
}
echo "BKU opening-saldo anchor test passed.\n";


require_once __DIR__ . '/../modules/spj_bosp/lib_spj_si.php';
$siCases = [
    [0, 'nol'],
    [1, 'satu'],
    [11, 'sebelas'],
    [15, 'lima belas'],
    [100, 'seratus'],
    [1250, 'seribu dua ratus lima puluh'],
    [1234567, 'satu juta dua ratus tiga puluh empat ribu lima ratus enam puluh tujuh'],
    [1000000000, 'satu miliar'],
    [2500000000, 'dua miliar lima ratus juta'],
    [1000000000000, 'satu triliun'],
];
foreach ($siCases as [$input, $expected]) {
    $actual = si_terbilang($input);
    if ($actual !== $expected) {
        fwrite(STDERR, 'FAIL si_terbilang(' . $input . '): got ' . var_export($actual, true) . ', expected ' . var_export($expected, true) . PHP_EOL);
        exit(1);
    }
}
echo 'SI terbilang tests passed: ' . count($siCases) . ' cases.' . PHP_EOL;
