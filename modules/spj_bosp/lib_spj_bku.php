<?php
declare(strict_types=1);

/** Normalize CSV header names for Indonesian BKU exports. */
function bku_norm(string $s): string
{
    $s = strtolower(trim(preg_replace('/\\x{FEFF}/u', '', $s) ?? $s));
    return preg_replace('/[^a-z0-9]+/', '', $s) ?? $s;
}

/** Parse Indonesian Rupiah formats; return null for invalid/negative values. */
function bku_money($raw): ?float
{
    $v = trim((string) $raw);
    if ($v === '') return 0.0;
    $v = str_ireplace(['rp', 'idr', ' ', "\t"], '', $v);
    if (str_contains($v, ',') && str_contains($v, '.')) {
        if (strrpos($v, ',') > strrpos($v, '.')) {
            $v = str_replace(',', '.', str_replace('.', '', $v));
        } else {
            $v = str_replace(',', '', $v);
        }
    } elseif (str_contains($v, ',')) {
        if (preg_match('/^\\d{1,3}(,\\d{3})+$/', $v)) $v = str_replace(',', '', $v);
        else $v = str_replace(',', '.', $v);
    } elseif (preg_match('/^\\d{1,3}(\\.\\d{3})+$/', $v)) {
        $v = str_replace('.', '', $v);
    }
    return is_numeric($v) && is_finite((float) $v) && (float) $v >= 0 ? (float) $v : null;
}

/** Classify a BKU row without treating tax movements as ordinary spending. */
function bku_classify(string $description, string $activity, float $outgoing, float $incoming): string
{
    $text = strtolower($description . ' ' . $activity);
    if (preg_match('/saldo awal|saldo sebelumnya|saldo akhir|saldo dibawa|saldo kas awal/i', $text)) return 'SALDO';
    if (preg_match('/terima\s*(pph|ppn)|penerimaan\s*(pph|ppn)|pemungutan\s*(pph|ppn)/i', $text)) return 'PENERIMAAN_PAJAK';
    if (preg_match('/setor\s*(pph|ppn)|setoran\s*(pph|ppn)|penyetoran\s*(pph|ppn)/i', $text)) return 'SETOR_PAJAK';
    if ($outgoing <= 0 && $incoming > 0) return 'PENERIMAAN';
    if ($outgoing <= 0) return 'LAINNYA';
    return 'PENGELUARAN';
}


/** Stable fingerprint for repeated rows within one BKU CSV preview. */
function bku_row_key(string $source, array $row): string
{
    $fields = [
        $source,
        (string) ($row['tanggal'] ?? ''),
        (string) ($row['nomor'] ?? ''),
        (string) ($row['jenis'] ?? ''),
        number_format((float) ($row['masuk'] ?? 0), 2, '.', ''),
        number_format((float) ($row['keluar'] ?? 0), 2, '.', ''),
        trim((string) ($row['uraian'] ?? '')),
    ];
    return hash('sha256', implode('|', $fields));
}


/** Difference between reported and calculated running balance. */
function bku_balance_delta(float $previous, float $incoming, float $outgoing, float $reported): float
{
    return round($reported - ($previous + $incoming - $outgoing), 2);
}


/**
 * Reconcile reported balances across rows with optional blank saldo cells.
 * Movements between two reported balances are accumulated before comparison.
 *
 * @return array{0: array, 1: int, 2: int} rows, comparisons, discrepancies
 */
function bku_reconcile_balances(array $rows): array
{
    $anchor = null;
    $movement = 0.0;
    $checks = 0;
    $warnings = 0;

    foreach ($rows as &$row) {
        $incoming = (float) ($row['masuk'] ?? 0);
        $outgoing = (float) ($row['keluar'] ?? 0);
        $reported = $row['saldo'] ?? null;

        if ($reported === null) {
            if ($anchor !== null && ($row['jenis'] ?? '') !== 'SALDO') {
                $movement += $incoming - $outgoing;
            }
            continue;
        }

        $reported = (float) $reported;
        // Opening/closing balance lines are anchors, not ordinary movements
        // to compare against the preceding row.
        if (($row['jenis'] ?? '') === 'SALDO') {
            $anchor = $reported;
            $movement = 0.0;
            continue;
        }

        if ($anchor !== null) {
            // Include this row's own movement when its saldo is reported.
            $delta = round($reported - ($anchor + $movement + $incoming - $outgoing), 2);
            $row['selisih_saldo'] = $delta;
            $checks++;
            if (abs($delta) > 0.01) $warnings++;
        }

        // Every reported running saldo becomes the next reconciliation anchor.
        $anchor = $reported;
        $movement = 0.0;
    }
    unset($row);

    return [$rows, $checks, $warnings];
}
