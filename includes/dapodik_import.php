<?php
/**
 * Reader sederhana CSV/XLSX untuk ekspor Dapodik.
 * XLSX membutuhkan ekstensi PHP ZipArchive dan SimpleXML (tersedia pada XAMPP standar).
 */
function dapodik_normalize_header($value): string {
    $value = preg_replace('/^\xEF\xBB\xBF/', '', (string)$value);
    $value = strtolower(trim($value));
    $value = preg_replace('/[\s_\-\/().]+/u', ' ', $value);
    return trim($value);
}
function dapodik_read_rows(string $path, string $originalName): array {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'csv' || $ext === 'txt') {
        $fh = fopen($path, 'r');
        if (!$fh) throw new RuntimeException('File CSV tidak dapat dibaca.');
        $first = fgets($fh);
        $delimiter = substr_count((string)$first, ';') > substr_count((string)$first, ',') ? ';' : ',';
        rewind($fh);
        $rows = [];
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) $rows[] = $row;
        fclose($fh);
    } elseif ($ext === 'xlsx') {
        if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
            throw new RuntimeException('Pembaca XLSX belum aktif. Aktifkan ekstensi zip dan SimpleXML di XAMPP.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('File Excel tidak valid atau rusak.');
        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $xml = simplexml_load_string($ss);
            if ($xml) foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $si) {
                $text = '';
                $plain = $si->xpath('./*[local-name()="t"]') ?: [];
                if ($plain) $text = (string)$plain[0];
                else foreach (($si->xpath('.//*[local-name()="r"]') ?: []) as $run) {
                    $t = $run->xpath('./*[local-name()="t"]') ?: [];
                    if ($t) $text .= (string)$t[0];
                }
                $shared[] = $text;
            }
        }
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook !== false && $rels !== false) {
            $wb = simplexml_load_string($workbook);
            $relXml = simplexml_load_string($rels);
            $sheets = $wb ? ($wb->xpath('//*[local-name()="sheet"]') ?: []) : [];
            if ($wb && $relXml && isset($sheets[0])) {
                $relNs = $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rid = (string)($relNs['id'] ?? '');
                $relationships = $relXml->xpath('//*[local-name()="Relationship"]') ?: [];
                foreach ($relationships as $rel) {
                    if ((string)$rel['Id'] === $rid) {
                        $target = ltrim((string)$rel['Target'], '/');
                        $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                        break;
                    }
                }
            }
        }
        $sheet = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheet === false) throw new RuntimeException('Lembar pertama Excel tidak ditemukan.');
        $sx = simplexml_load_string($sheet);
        if (!$sx) throw new RuntimeException('Isi lembar Excel tidak dapat dibaca.');
        $rows = [];
        $sheetRows = $sx->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [];
        foreach ($sheetRows as $rowNode) {
            $row = [];
            foreach (($rowNode->xpath('./*[local-name()="c"]') ?: []) as $cell) {
                $ref = (string)$cell['r'];
                preg_match('/^[A-Z]+/', $ref, $m);
                $letters = $m[0] ?? 'A'; $col = 0;
                foreach (str_split($letters) as $letter) $col = $col * 26 + ord($letter) - 64;
                $col--;
                $type = (string)$cell['t'];
                $v = $cell->xpath('./*[local-name()="v"]') ?: [];
                $value = $v ? (string)$v[0] : '';
                if ($type === 's') $value = $shared[(int)$value] ?? '';
                elseif ($type === 'inlineStr') {
                    $t = $cell->xpath('.//*[local-name()="t"]') ?: [];
                    $value = $t ? (string)$t[0] : '';
                }
                $row[$col] = $value;
            }
            if ($row) {
                $max = max(array_keys($row));
                $full = array_fill(0, $max + 1, '');
                foreach ($row as $i => $value) $full[$i] = $value;
                $rows[] = $full;
            } else $rows[] = [];
        }
    } else {
        throw new RuntimeException('Format file tidak didukung. Unggah file .xlsx atau .csv hasil ekspor Dapodik.');
    }
    if (!$rows || !isset($rows[0])) throw new RuntimeException('File tidak memiliki baris judul kolom.');
    return $rows;
}
function dapodik_canonical_headers(array $headers, string $kind): array {
    $aliases = [
        'nama' => ['nama','nama peserta didik','nama gtk','nama guru','nama lengkap'],
        'nip' => ['nip','nip gtk','nip ptk','nomor induk pegawai'],
        'jabatan' => ['jabatan','jenis ptk','jenis gtk','peran','jabatan ptk'],
        'nisn' => ['nisn','nomor induk siswa nasional'],
        'nis' => ['nis','nisd','nipd','nomor induk peserta didik','nomor induk siswa'],
        'rombel' => ['rombel','rombel saat ini','nama rombel','rombongan belajar','kelas'],
        'jenis_kelamin' => ['jenis kelamin','jk','l p','gender'],
        'status' => ['status','status keaktifan','status gtk','status peserta didik','status kepegawaian','status kepegawaian gtk'],
    ];
    $result = [];
    foreach ($headers as $i => $header) {
        $normalized = dapodik_normalize_header($header);
        foreach ($aliases as $canonical => $names) {
            if (in_array($normalized, array_map('dapodik_normalize_header', $names), true)) {
                if (!isset($result[$canonical])) $result[$canonical] = $i;
                break;
            }
        }
    }
    if ($kind === 'guru' && !isset($result['jabatan'])) {
        foreach ($headers as $i => $header) {
            $n = dapodik_normalize_header($header);
            if (str_contains($n, 'jenis ptk') || str_contains($n, 'jenis gtk')) { $result['jabatan'] = $i; break; }
        }
    }
    return $result;
}
