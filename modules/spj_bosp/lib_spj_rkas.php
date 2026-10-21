<?php
declare(strict_types=1);

/** Parse Indonesian Rupiah values from RKAS CSV exports. */
function rkas_money($raw): ?float
{
    $v = trim((string) $raw);
    if ($v === '') return null;
    $v = str_replace(['Rp', 'rp', ' ',"\t"], '', $v);
    if (str_contains($v, ',') && str_contains($v, '.')) {
        if (strrpos($v, ',') > strrpos($v, '.')) $v = str_replace(',', '.', str_replace('.', '', $v));
        else $v = str_replace(',', '', $v);
    } elseif (str_contains($v, ',')) {
        if (preg_match('/^\\d{1,3}(,\\d{3})+$/', $v)) $v = str_replace(',', '', $v);
        else $v = str_replace(',', '.', $v);
    } elseif (preg_match('/^\\d{1,3}(\\.\\d{3})+$/', $v)) {
        $v = str_replace('.', '', $v);
    }
    return is_numeric($v) && is_finite((float) $v) && (float) $v >= 0 ? (float) $v : null;
}

/** Normalize CSV header names, including a UTF-8 BOM. */
function rkas_norm(string $s): string
{
    $s = strtolower(trim(preg_replace('/\\x{FEFF}/u', '', $s) ?? $s));
    return preg_replace('/[^a-z0-9]+/', '', $s) ?? $s;
}


/**
 * Extract line-item budgets from a text-based RKAS PDF using Poppler's pdftotext.
 * Only numbered detail rows (e.g. 001., 002.) are imported to avoid double-counting
 * parent/subtotal rows. Returns CSV text so the existing validation/preview pipeline
 * remains the single path for both CSV and PDF imports.
 */
function rkas_pdf_to_csv(string $path, string $source): string
{
    if (!in_array($source, ['BOSP', 'BOS_KINERJA'], true)) {
        throw new RuntimeException('Sumber dana PDF tidak valid.');
    }
    $fh = @fopen($path, 'rb');
    $signature = $fh ? fread($fh, 5) : false;
    if ($fh) fclose($fh);
    if ($signature !== '%PDF-') {
        throw new RuntimeException('File bukan PDF yang valid.');
    }
    if (!function_exists('exec')) {
        throw new RuntimeException('Server tidak mengizinkan ekstraksi PDF. Gunakan CSV UTF-8 atau aktifkan pdftotext pada hosting.');
    }
    $output = [];
    $status = 1;
    try {
        exec('pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>&1', $output, $status);
    } catch (Throwable $e) {
        throw new RuntimeException('Ekstraksi PDF gagal. Pastikan utilitas pdftotext tersedia di server.');
    }
    $text = implode("\n", $output);
    if ($status !== 0 || trim($text) === '' || stripos($text, 'command not found') !== false || stripos($text, 'not recognized as an internal') !== false) {
        throw new RuntimeException('PDF belum dapat dibaca oleh server. Pastikan pdftotext tersedia dan PDF berisi teks, bukan hasil scan gambar.');
    }

    $lines = preg_split('/\R/u', $text) ?: [];
    $rows = [];
    $last = null;
    $accountPattern = '5\\.\\d{1,2}\\.\\d{2}\\.\\d{2}\\.\\d{2}\\.\\d{4}';
    $codePattern = '(?:\\d{2}\\.){1,4}';
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '') continue;
        $matched = preg_match('/^\\d+\\s+(' . $accountPattern . ')\\s+(' . $codePattern . ')\\s+(\\d{3})\\.\\s+(.+?)\\s+((?:\\d[\\d.]*\\s+){12}\\d[\\d.]*)$/u', $line, $m);
        if ($matched === 1) {
            preg_match_all('/\\d[\\d.]*/', $m[5], $amountMatches);
            $amounts = $amountMatches[0] ?? [];
            if (count($amounts) !== 13) { $last = null; continue; }
            $opIndex = $source === 'BOS_KINERJA' ? 7 : 1;
            $modalIndex = $source === 'BOS_KINERJA' ? 8 : 2;
            $operation = rkas_money($amounts[$opIndex] ?? '');
            $capital = rkas_money($amounts[$modalIndex] ?? '');
            if ($operation === null || $capital === null) { $last = null; continue; }
            $description = trim((string) $m[4]);
            $code = trim((string) $m[2] . ' ' . (string) $m[3]);
            $account = trim((string) $m[1]);
            $last = null;
            if ($operation > 0 && $capital > 0) {
                $rows[] = ['kode_kegiatan'=>$code.'-O','kode_rekening'=>$account,'uraian'=>$description.' (Operasi)','jenis_belanja'=>'OPERASI','pagu'=>$operation];
                $rows[] = ['kode_kegiatan'=>$code.'-M','kode_rekening'=>$account,'uraian'=>$description.' (Modal)','jenis_belanja'=>'MODAL','pagu'=>$capital];
                $last = count($rows) - 2;
            } elseif ($operation > 0) {
                $rows[] = ['kode_kegiatan'=>$code,'kode_rekening'=>$account,'uraian'=>$description,'jenis_belanja'=>'OPERASI','pagu'=>$operation];
                $last = count($rows) - 1;
            } elseif ($capital > 0) {
                $rows[] = ['kode_kegiatan'=>$code,'kode_rekening'=>$account,'uraian'=>$description,'jenis_belanja'=>'MODAL','pagu'=>$capital];
                $last = count($rows) - 1;
            }
            continue;
        }
        // Subtotal rows and page furniture end any wrapped item description.
        if (preg_match('/^\\d+\\s+/', $line)
            || preg_match('/^(Kertas Kerja|Halaman|Sumber Dana|No\\.|Urut|Kode Rekening|KERTAS KERJA|A\\.|B\\.|Kec\\.|Komite Sekolah|Kepala Sekolah|Bendahara Sekolah|NIP:)/iu', $line)
            || preg_match('/^(Belanja|Operasi|Modal)\\s+/iu', $line)) {
            $last = null;
            continue;
        }
        // Wrapped descriptions usually continue on the next indented line.
        if ($last !== null && !preg_match('/\\d{1,3}(?:\\.\\d{3})+/', $line)) {
            $rows[$last]['uraian'] = trim($rows[$last]['uraian'] . ' ' . $line);
        }
    }
    if (!$rows) {
        throw new RuntimeException('Tidak ditemukan baris rincian anggaran PDF. Pastikan PDF RKAS berbasis teks dan memiliki kolom alokasi BOSP Reguler/Kinerja.');
    }
    $csv = fopen('php://temp', 'r+');
    if (!$csv) throw new RuntimeException('Gagal menyiapkan data hasil ekstraksi PDF.');
    fputcsv($csv, ['kode_kegiatan','kode_rekening','uraian','jenis_belanja','pagu']);
    foreach ($rows as $row) {
        fputcsv($csv, [$row['kode_kegiatan'],$row['kode_rekening'],$row['uraian'],$row['jenis_belanja'],number_format((float)$row['pagu'], 2, '.', '')]);
    }
    rewind($csv);
    $result = stream_get_contents($csv);
    fclose($csv);
    if (!is_string($result) || $result === '') throw new RuntimeException('Data PDF gagal dikonversi untuk pratinjau.');
    return $result;
}
