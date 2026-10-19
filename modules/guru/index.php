<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/dapodik_import.php';
$pageTitle = 'Data Guru';
$errors = [];
$edit = null;

if (($_GET['download_template'] ?? '') === 'guru') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="template_data_guru.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['nip','nama','jabatan','status']);
    fputcsv($out, ['198001012005011001','Nama Guru Contoh','Guru Kelas','aktif']);
    fclose($out); exit;
}
$importResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Pilih file CSV yang valid untuk diunggah.';
    } elseif ($_FILES['csv_file']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Ukuran file maksimal 5 MB.';
    } else {
        try {
            $rows = dapodik_read_rows($_FILES['csv_file']['tmp_name'], $_FILES['csv_file']['name'] ?? '');
            $headers = array_shift($rows) ?? [];
            $map = dapodik_canonical_headers($headers, 'guru');
            if (!isset($map['nama'])) {
                $errors[] = 'Kolom Nama tidak ditemukan pada file Dapodik. Pastikan menggunakan file ekspor GTK.';
            } else {
                $ok = 0; $skip = 0;
                $pdo->beginTransaction();
                $find = $pdo->prepare('SELECT id FROM guru WHERE nip=? LIMIT 1');
                $update = $pdo->prepare('UPDATE guru SET nama=?,jabatan=?,status=? WHERE id=?');
                $insert = $pdo->prepare('INSERT INTO guru (nip,nama,jabatan,status) VALUES (?,?,?,?)');
                foreach ($rows as $row) {
                    if (!is_array($row) || count(array_filter($row, static fn($v) => trim((string)$v) !== '')) === 0) continue;
                    $get = static function($key) use ($row,$map) { return isset($map[$key]) ? trim((string)($row[$map[$key]] ?? '')) : ''; };
                    $nip=$get('nip'); $nama=$get('nama'); $jabatan=$get('jabatan');
                    $rawStatus=strtolower($get('status'));
                    $status=(str_contains($rawStatus,'nonaktif') || str_contains($rawStatus,'keluar')) ? 'nonaktif' : 'aktif';
                    if ($nama === '') { $skip++; continue; }
                    $existing=false;
                    if ($nip !== '') { $find->execute([$nip]); $existing=$find->fetchColumn(); }
                    if ($existing) $update->execute([$nama,$jabatan ?: null,$status,(int)$existing]);
                    else $insert->execute([$nip ?: null,$nama,$jabatan ?: null,$status]);
                    $ok++;
                }
                $pdo->commit();
                $importResult = "Impor Dapodik GTK selesai: {$ok} baris diproses, {$skip} baris dilewati karena nama kosong.";
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Impor gagal: '. $e->getMessage();
        }
    }
}



if (!$pdo) {
    http_response_code(500);
    require __DIR__ . '/../../includes/header.php';
    echo '<div class="notice warning">Database belum tersambung. Periksa config/database.php.</div>';
    require __DIR__ . '/../../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM guru WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: index.php?deleted=1'); exit;
    }
    if ($action === 'save') {
        $nip = trim($_POST['nip'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
        if ($nama === '') $errors[] = 'Nama guru wajib diisi.';
        if (!$errors) {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE guru SET nip=?, nama=?, jabatan=?, status=? WHERE id=?');
                $stmt->execute([$nip ?: null, $nama, $jabatan ?: null, $status, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO guru (nip,nama,jabatan,status) VALUES (?,?,?,?)');
                $stmt->execute([$nip ?: null, $nama, $jabatan ?: null, $status]);
            }
            header('Location: index.php?saved=1'); exit;
        }
        $edit = ['id'=>$id,'nip'=>$nip,'nama'=>$nama,'jabatan'=>$jabatan,'status'=>$status];
    }
}

if (!$edit && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id,nip,nama,jabatan,status FROM guru WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare('SELECT id,nip,nama,jabatan,status FROM guru WHERE nama LIKE ? OR nip LIKE ? ORDER BY nama');
    $like = '%' . $q . '%'; $stmt->execute([$like,$like]); $gurus = $stmt->fetchAll();
} else {
    $gurus = $pdo->query('SELECT id,nip,nama,jabatan,status FROM guru ORDER BY nama')->fetchAll();
}
require __DIR__ . '/../../includes/header.php';
?>
<section class="welcome"><div><span class="eyebrow">MASTER DATA</span><h2>Data Guru</h2><p>Kelola data tenaga pendidik sekolah.</p></div><div class="date-chip"><?= count($gurus) ?> data guru</div></section>
<?php if (isset($_GET['saved'])): ?><div class="notice" style="background:#e8f8f1;color:#13845e">Data guru berhasil disimpan.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="notice" style="background:#e8f8f1;color:#13845e">Data guru berhasil dihapus.</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="notice warning"><?= h($error) ?></div><?php endforeach; ?>
<?php if ($importResult): ?><div class="notice" style="background:#e8f8f1;color:#13845e"><?= h($importResult) ?></div><?php endif; ?>
<section class="panel" style="margin-bottom:20px"><div class="panel-heading"><div><h3>Upload Data Guru Massal</h3><p>CSV kolom nip, nama, jabatan, status. Maksimal 5 MB; NIP yang sudah ada diperbarui.</p></div></div>
<p style="margin:0 0 12px"><span style="color:#536179">Gunakan file ekspor GTK asli dari Dapodik; tidak perlu template khusus.</span></p>
<form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center"><input type="hidden" name="action" value="import_csv"><input type="file" name="csv_file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><button type="submit" onclick="return confirm('Proses upload data guru dari CSV?')" style="padding:11px 16px;background:#0f8b68;color:white;border:0;border-radius:8px;font-weight:700;cursor:pointer">Upload Data Massal</button></form></section>
<section class="panel" style="margin-bottom:20px">
<div class="panel-heading"><div><h3><?= $edit ? 'Edit Guru' : 'Tambah Guru' ?></h3><p>Nama wajib diisi; NIP dan jabatan boleh dikosongkan.</p></div></div>
<form method="post" action="index.php" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end">
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
<label>NIP<input name="nip" value="<?= h($edit['nip'] ?? '') ?>" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<label>Nama lengkap *<input required name="nama" value="<?= h($edit['nama'] ?? '') ?>" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<label>Jabatan<input name="jabatan" value="<?= h($edit['jabatan'] ?? '') ?>" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<label>Status<select name="status" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"><option value="aktif" <?= ($edit['status'] ?? 'aktif')==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= ($edit['status'] ?? '')==='nonaktif'?'selected':'' ?>>Nonaktif</option></select></label>
<div style="display:flex;gap:8px"><button type="submit" style="padding:11px 16px;background:#4263eb;color:white;border:0;border-radius:8px;font-weight:700;cursor:pointer">Simpan</button><?php if ($edit): ?><a href="index.php" style="padding:11px 16px;background:#edf1f7;border-radius:8px">Batal</a><?php endif; ?></div>
</form></section>
<section class="panel"><div class="panel-heading"><div><h3>Daftar Guru</h3><p>Data tersimpan pada database lokal.</p></div><form method="get" style="display:flex;gap:8px"><input name="q" placeholder="Cari nama/NIP..." value="<?= h($q) ?>" style="padding:9px;border:1px solid #dce3ed;border-radius:8px"><button style="padding:9px 13px;border:0;border-radius:8px;background:#edf1ff;color:#3556cf;cursor:pointer">Cari</button></form></div>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><?php foreach (['NIP','Nama','Jabatan','Status','Aksi'] as $heading): ?><th style="padding:12px;border-bottom:1px solid #e8edf4;color:#718096;font-size:12px"><?= h($heading) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php if (!$gurus): ?><tr><td colspan="5" style="padding:20px;color:#8290a5;text-align:center">Belum ada data guru.</td></tr><?php endif; ?>
<?php foreach ($gurus as $guru): ?><tr><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($guru['nip'] ?? '-') ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6;font-weight:600"><?= h($guru['nama']) ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($guru['jabatan'] ?? '-') ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h(ucfirst($guru['status'])) ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6;white-space:nowrap"><a href="index.php?edit=<?= (int)$guru['id'] ?>" style="color:#4263eb;font-weight:700">Edit</a> <form method="post" style="display:inline" onsubmit="return confirm('Hapus data guru ini?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$guru['id'] ?>"><button style="margin-left:8px;border:0;background:none;color:#d34b4b;cursor:pointer">Hapus</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
