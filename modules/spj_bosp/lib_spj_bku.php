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
