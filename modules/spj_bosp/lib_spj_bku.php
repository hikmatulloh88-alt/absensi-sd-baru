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
