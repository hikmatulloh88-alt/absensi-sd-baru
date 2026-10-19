<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/dapodik_import.php';
$pageTitle = 'Data Siswa';
$errors = [];
$edit = null;

if (($_GET['download_template'] ?? '') === 'siswa') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="template_data_siswa.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['nisn','nis','nama','rombel','jenis_kelamin','status']);
    fputcsv($out, ['0123456789','1001','Nama Siswa Contoh','1A','L','aktif']);
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
            $map = dapodik_canonical_headers($headers, 'siswa');
            if (!isset($map['nama'])) {
                $errors[] = 'Kolom Nama tidak ditemukan pada file Dapodik peserta didik.';
            } else {
                $ok = 0; $skip = 0;
                $pdo->beginTransaction();
                $findNisn = $pdo->prepare('SELECT id FROM siswa WHERE nisn=? LIMIT 1');
                $findNis = $pdo->prepare('SELECT id FROM siswa WHERE nis=? LIMIT 1');
                $update = $pdo->prepare('UPDATE siswa SET nisn=?,nis=?,nama=?,rombel=?,jenis_kelamin=?,status=? WHERE id=?');
                $insertStmt = $pdo->prepare('INSERT INTO siswa (nisn,nis,nama,rombel,jenis_kelamin,status) VALUES (?,?,?,?,?,?)');
                foreach ($rows as $row) {
                    if (!is_array($row) || count(array_filter($row, static fn($v) => trim((string)$v) !== '')) === 0) continue;
                    $get = static function($key) use ($row,$map) { return isset($map[$key]) ? trim((string)($row[$map[$key]] ?? '')) : ''; };
                    $nisn=$get('nisn'); $nis=$get('nis'); $nama=$get('nama'); $rombel=$get('rombel');
                    $jkRaw=strtoupper($get('jenis_kelamin'));
                    $jk=in_array($jkRaw,['L','P'],true) ? $jkRaw : (str_contains($jkRaw,'LAKI') ? 'L' : (str_contains($jkRaw,'PEREMPUAN') ? 'P' : null));
                    $rawStatus=strtolower($get('status'));
                    $status=(str_contains($rawStatus,'nonaktif') || str_contains($rawStatus,'keluar') || str_contains($rawStatus,'lulus')) ? 'nonaktif' : 'aktif';
                    if ($nama === '') { $skip++; continue; }
                    $existing=false;
                    if ($nisn !== '') { $findNisn->execute([$nisn]); $existing=$findNisn->fetchColumn(); }
                    if (!$existing && $nis !== '') { $findNis->execute([$nis]); $existing=$findNis->fetchColumn(); }
                    if ($existing) $update->execute([$nisn ?: null,$nis ?: null,$nama,$rombel ?: null,$jk,$status,(int)$existing]);
                    else $insertStmt->execute([$nisn ?: null,$nis ?: null,$nama,$rombel ?: null,$jk,$status]);
                    $ok++;
                }
                $pdo->commit();
                $importResult = "Impor Dapodik siswa selesai: {$ok} baris diproses, {$skip} baris dilewati karena nama kosong.";
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_photos_bulk') {
    $files = $_FILES['photos'] ?? null;
    $maxFiles = 500;
    $allowedExtensions = ['jpg','jpeg','png','webp'];
    if (!$files || !isset($files['name']) || !is_array($files['name']) || count($files['name']) === 0) {
        $errors[] = 'Pilih foto siswa terlebih dahulu.';
    } elseif (count($files['name']) > $maxFiles) {
        $errors[] = 'Maksimal 500 foto dalam satu kali upload.';
    } else {
        $photoDir = __DIR__ . '/../../assets/images/siswa/';
        if (!is_dir($photoDir) && !mkdir($photoDir, 0755, true) && !is_dir($photoDir)) {
            $errors[] = 'Folder foto siswa gagal dibuat.';
        } else {
            $okCount = 0; $failCount = 0; $details = [];
            $findStudent = $pdo->prepare('SELECT id FROM siswa WHERE id=? OR nis=? OR nisn=? LIMIT 1');
            foreach ($files['name'] as $i => $originalName) {
                $uploadError = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ($uploadError !== UPLOAD_ERR_OK) { $failCount++; $details[] = $originalName . ': gagal diunggah'; continue; }
                if (($files['size'][$i] ?? 0) > 3 * 1024 * 1024) { $failCount++; $details[] = $originalName . ': lebih dari 3 MB'; continue; }
                $tmp = $files['tmp_name'][$i] ?? '';
                $info = pathinfo($originalName);
                $code = trim((string)($info['filename'] ?? ''));
                $extension = strtolower((string)($info['extension'] ?? ''));
                if (!in_array($extension, $allowedExtensions, true)) { $failCount++; $details[] = $originalName . ': format harus JPG, PNG, WEBP'; continue; }
                $imageInfo = @getimagesize($tmp);
                $typeMap = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
                $imageType = $imageInfo[2] ?? 0;
                if (!$imageInfo || !isset($typeMap[$imageType])) { $failCount++; $details[] = $originalName . ': file bukan gambar valid'; continue; }
                $idFromName = ctype_digit($code) ? (int)$code : 0;
                $findStudent->execute([$idFromName, $code, $code]);
                $studentId = (int)($findStudent->fetchColumn() ?: 0);
                if ($studentId <= 0) { $failCount++; $details[] = $originalName . ': nama file harus ID, NIS, atau NISN siswa'; continue; }
                $saveExt = $typeMap[$imageType];
                $target = $photoDir . $studentId . '.' . $saveExt;
                foreach (['jpg','jpeg','png','webp'] as $oldExt) {
                    $oldPath = $photoDir . $studentId . '.' . $oldExt;
                    if ($oldPath !== $target && is_file($oldPath)) @unlink($oldPath);
                }
                if (move_uploaded_file($tmp, $target)) { $okCount++; }
                else { $failCount++; $details[] = $originalName . ': gagal disimpan'; }
            }
            $importResult = $okCount . ' foto berhasil diunggah' . ($failCount ? ', ' . $failCount . ' gagal: ' . implode('; ', array_slice($details, 0, 6)) : '.') ;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_photo') {
    $photoId = (int)($_POST['id'] ?? 0);
    $photo = $_FILES['photo'] ?? null;
    if ($photoId <= 0 || !$photo || ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Pilih foto siswa yang valid.';
    } elseif (($photo['size'] ?? 0) > 3 * 1024 * 1024) {
        $errors[] = 'Ukuran foto maksimal 3 MB.';
    } else {
        $imageInfo = @getimagesize($photo['tmp_name']);
        $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        $imageType = $imageInfo[2] ?? 0;
        if (!$imageInfo || !isset($allowedTypes[$imageType])) {
            $errors[] = 'Foto harus berformat JPG, PNG, atau WEBP.';
        } else {
            $photoDir = __DIR__ . '/../../assets/images/siswa/';
            if (!is_dir($photoDir) && !mkdir($photoDir, 0755, true) && !is_dir($photoDir)) {
                $errors[] = 'Folder foto siswa gagal dibuat.';
            } else {
                $extension = $allowedTypes[$imageType];
                $photoPath = $photoDir . $photoId . '.' . $extension;
                foreach (['jpg','jpeg','png','webp'] as $oldExt) {
                    $oldPath = $photoDir . $photoId . '.' . $oldExt;
                    if ($oldPath !== $photoPath && is_file($oldPath)) @unlink($oldPath);
                }
                if (move_uploaded_file($photo['tmp_name'], $photoPath)) {
                    header('Location: index.php?photo_saved=1'); exit;
                }
                $errors[] = 'Foto gagal disimpan ke server.';
            }
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM siswa WHERE id=?');
        $stmt->execute([$id]);
        header('Location: index.php?deleted=1'); exit;
    }
    if ($action === 'save') {
        $nisn = trim($_POST['nisn'] ?? '');
        $nis = trim($_POST['nis'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $rombel = trim($_POST['rombel'] ?? '');
        $jk = $_POST['jenis_kelamin'] ?? '';
        $jk = in_array($jk, ['L','P'], true) ? $jk : null;
        $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
        if ($nama === '') $errors[] = 'Nama siswa wajib diisi.';
        if (!$errors) {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE siswa SET nisn=?,nis=?,nama=?,rombel=?,jenis_kelamin=?,status=? WHERE id=?');
                $stmt->execute([$nisn ?: null,$nis ?: null,$nama,$rombel ?: null,$jk,$status,$id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO siswa (nisn,nis,nama,rombel,jenis_kelamin,status) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$nisn ?: null,$nis ?: null,$nama,$rombel ?: null,$jk,$status]);
            }
            header('Location: index.php?saved=1'); exit;
        }
        $edit = ['id'=>$id,'nisn'=>$nisn,'nis'=>$nis,'nama'=>$nama,'rombel'=>$rombel,'jenis_kelamin'=>$jk,'status'=>$status];
    }
}
if (!$edit && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id,nisn,nis,nama,rombel,jenis_kelamin,status FROM siswa WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]); $edit = $stmt->fetch() ?: null;
}
$q = trim($_GET['q'] ?? '');
$rombelFilter = trim($_GET['rombel'] ?? '');
$sql = 'SELECT id,nisn,nis,nama,rombel,jenis_kelamin,status FROM siswa WHERE 1=1';
$params = [];
if ($q !== '') { $sql .= ' AND (nama LIKE ? OR nis LIKE ? OR nisn LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
if ($rombelFilter !== '') { $sql .= ' AND rombel=?'; $params[]=$rombelFilter; }
$sql .= ' ORDER BY rombel,nama';
$stmt=$pdo->prepare($sql); $stmt->execute($params); $siswas=$stmt->fetchAll();
$rombels=$pdo->query("SELECT DISTINCT rombel FROM siswa WHERE rombel IS NOT NULL AND rombel<>'' ORDER BY rombel")->fetchAll(PDO::FETCH_COLUMN);
require __DIR__ . '/../../includes/header.php';
$totalSiswa = (int)$pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$statGender = $pdo->query("SELECT SUM(jenis_kelamin='L') AS laki, SUM(jenis_kelamin='P') AS perempuan FROM siswa")->fetch() ?: [];
$totalRombel = (int)$pdo->query("SELECT COUNT(DISTINCT rombel) FROM siswa WHERE rombel IS NOT NULL AND rombel<>''")->fetchColumn();
?>
<style>
.student-page{--sp-blue:#1677f2;--sp-ink:#132238;--sp-muted:#728198;color:var(--sp-ink)}
.student-page .welcome{align-items:center;margin-bottom:22px}
.student-page .welcome h2{font-size:30px;letter-spacing:-.7px;margin:3px 0 6px}
.student-page .eyebrow{font-size:11px;font-weight:800;letter-spacing:1.5px;color:#1677f2}
.student-page .welcome p{color:var(--sp-muted)}
.student-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:0 0 20px}
.student-stat{padding:20px;border:1px solid #e6edf5;border-radius:14px;background:#fff;box-shadow:0 5px 18px #243b5308;min-width:0}
.student-stat .label{font-size:13px;color:#66778e;font-weight:600}
.student-stat .value{font-size:29px;font-weight:800;letter-spacing:-.8px;margin:7px 0 4px}
.student-stat .hint{font-size:12px;color:#7b8ba1}
.student-stat .stat-icon{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:12px;font-size:21px;margin-bottom:12px}
.student-stat.blue{background:linear-gradient(135deg,#f1f7ff,#fff);border-color:#d8e9ff}.student-stat.blue .stat-icon{background:#dcecff;color:#1677f2}
.student-stat.green{background:linear-gradient(135deg,#effcf5,#fff);border-color:#d7f4e3}.student-stat.green .stat-icon{background:#d9f8e5;color:#159455}
.student-stat.pink{background:linear-gradient(135deg,#fff3f6,#fff);border-color:#ffe0e8}.student-stat.pink .stat-icon{background:#ffe0e8;color:#d6336c}
.student-stat.amber{background:linear-gradient(135deg,#fff9e9,#fff);border-color:#ffedc2}.student-stat.amber .stat-icon{background:#ffefc2;color:#b77908}
.student-page .panel{border:1px solid #e7edf5;border-radius:14px;box-shadow:0 5px 18px #243b5308;padding:20px;background:#fff}
.student-page .panel-heading{gap:14px;align-items:center}
.student-page .panel-heading h3{font-size:17px;letter-spacing:-.2px}
.student-page input,.student-page select{max-width:100%;box-sizing:border-box}
.student-page table tbody tr:hover{background:#f7faff}
.student-page table tbody td{font-size:13px}
.student-page table thead th{background:#f5f8fc;white-space:nowrap}
.student-page .action-primary{background:#1677f2!important;border-radius:9px!important}
.student-upload-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:0 0 20px}
.student-upload-card{position:relative;display:flex;flex-direction:column;gap:15px;min-width:0;padding:20px;border:1px solid #e5ebf4;border-radius:15px;background:#fff;box-shadow:0 5px 18px #243b5308;overflow:hidden}
.student-upload-card:before{content:"";position:absolute;inset:0 0 auto;height:4px;background:#1677f2}
.photo-upload-card{background:linear-gradient(135deg,#f2f8ff 0%,#fff 70%);border-color:#d9e9ff}.photo-upload-card:before{background:linear-gradient(90deg,#1677f2,#55a2ff)}
.data-upload-card{background:linear-gradient(135deg,#effcf5 0%,#fff 70%);border-color:#d5f2e2}.data-upload-card:before{background:linear-gradient(90deg,#11966d,#48c99a)}
.student-upload-icon{width:44px;height:44px;display:grid;place-items:center;border-radius:13px;font-size:22px}
.photo-upload-icon{background:#dcecff}.data-upload-icon{background:#d9f8e5}
.student-upload-copy{min-width:0}.student-upload-kicker{font-size:10px;font-weight:800;letter-spacing:1.25px;color:#6e8099}
.student-upload-copy h3{margin:6px 0 7px;font-size:17px;letter-spacing:-.25px;color:#172a45}
.student-upload-copy p{margin:0;color:#718198;font-size:12px;line-height:1.55}
.student-upload-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:auto}
.student-upload-form input[type=file]{min-width:0;flex:1 1 180px;width:100%;font-size:12px;color:#61728a}
.student-upload-form button{display:inline-flex;align-items:center;justify-content:center;gap:9px;border:0;border-radius:9px;padding:11px 15px;background:#1677f2;color:white;font-weight:700;font-size:12px;cursor:pointer;white-space:nowrap}
.data-upload-card .student-upload-form button{background:#108764}
.student-upload-form button:hover{filter:brightness(.96)}
@media(max-width:850px){.student-upload-grid{grid-template-columns:1fr}}
.student-page .student-filter-form{display:flex;align-items:center;gap:8px;flex-wrap:nowrap;min-width:0;margin:0}
.student-page .student-filter-form input{width:190px;min-width:130px;padding:10px 11px;border:1px solid #dce3ed;border-radius:8px;background:#fff}
.student-page .student-filter-form select{width:128px;min-width:115px;padding:10px 9px;border:1px solid #dce3ed;border-radius:8px;background:#fff}
.student-page .student-filter-form button{flex:0 0 auto;padding:10px 14px;border:0;border-radius:8px;background:#edf1ff;color:#3556cf;font-weight:700;cursor:pointer}
@media(max-width:1100px){.student-page .panel-heading{flex-wrap:wrap}.student-page .student-filter-form{width:100%}.student-page .student-filter-form input{flex:1;width:auto}}
@media(max-width:520px){.student-page .student-filter-form{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.student-page .student-filter-form input{grid-column:1/-1;width:100%;min-width:0}.student-page .student-filter-form select{width:100%;min-width:0}.student-page .student-filter-form button{width:100%}}
@media(max-width:900px){.student-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.student-stats{grid-template-columns:1fr 1fr;gap:8px}.student-stat{padding:13px}.student-stat .value{font-size:23px}.student-page .panel{padding:13px}.student-page .welcome h2{font-size:25px}}
</style>
<div class="student-page">
<section class="welcome"><div><span class="eyebrow">MASTER DATA</span><h2>Data Siswa</h2><p>Kelola data peserta didik, impor Dapodik, dan pantau rombongan belajar.</p></div><div class="date-chip">Tahun Ajaran 2026/2027</div></section>
<section class="student-stats">
<div class="student-stat blue"><div class="stat-icon">👥</div><div class="label">Total Siswa</div><div class="value"><?= number_format($totalSiswa,0,',','.') ?></div><div class="hint">Semua data peserta didik</div></div>
<div class="student-stat green"><div class="stat-icon">♂</div><div class="label">Laki-laki</div><div class="value"><?= number_format((int)($statGender['laki']??0),0,',','.') ?></div><div class="hint">Berdasarkan data jenis kelamin</div></div>
<div class="student-stat pink"><div class="stat-icon">♀</div><div class="label">Perempuan</div><div class="value"><?= number_format((int)($statGender['perempuan']??0),0,',','.') ?></div><div class="hint">Berdasarkan data jenis kelamin</div></div>
<div class="student-stat amber"><div class="stat-icon">🏫</div><div class="label">Rombel Aktif</div><div class="value"><?= number_format($totalRombel,0,',','.') ?></div><div class="hint">Rombongan belajar terdata</div></div>
</section>
<div class="student-list-count"><?= count($siswas) ?> data siswa ditampilkan</div>
<?php if(isset($_GET['photo_saved'])): ?><div class="notice" style="background:#e8f8f1;color:#13845e">Foto siswa berhasil diperbarui.</div><?php endif; ?>
<?php if(isset($_GET['saved'])): ?><div class="notice" style="background:#e8f8f1;color:#13845e">Data siswa berhasil disimpan.</div><?php endif; ?>
<?php if(isset($_GET['deleted'])): ?><div class="notice" style="background:#e8f8f1;color:#13845e">Data siswa berhasil dihapus.</div><?php endif; ?>
<?php foreach($errors as $error): ?><div class="notice warning"><?= h($error) ?></div><?php endforeach; ?>
<?php if ($importResult): ?><div class="notice" style="background:#e8f8f1;color:#13845e"><?= h($importResult) ?></div><?php endif; ?>
<section class="student-upload-grid">
<div class="student-upload-card photo-upload-card">
  <div class="student-upload-icon photo-upload-icon">📷</div>
  <div class="student-upload-copy"><span class="student-upload-kicker">FOTO PESERTA DIDIK</span><h3>Upload Foto Siswa Massal</h3><p>Pilih banyak foto sekaligus. Nama file harus sama dengan ID siswa, NIS, atau NISN. Maksimal 500 foto, 3 MB per foto.</p></div>
  <form method="post" enctype="multipart/form-data" class="student-upload-form"><input type="hidden" name="action" value="upload_photos_bulk"><input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required><button type="submit" onclick="return confirm('Upload foto siswa yang dipilih?')">Upload Foto Massal <span>→</span></button></form>
</div>
<div class="student-upload-card data-upload-card">
  <div class="student-upload-icon data-upload-icon">📋</div>
  <div class="student-upload-copy"><span class="student-upload-kicker">IMPOR DATA</span><h3>Upload Data Siswa Massal</h3><p>Gunakan file ekspor Peserta Didik asli dari Dapodik (.xlsx/CSV), tanpa mengubah susunan kolom. Format 46/66 kolom didukung; maksimal 5 MB.</p></div>
  <form method="post" enctype="multipart/form-data" class="student-upload-form"><input type="hidden" name="action" value="import_csv"><input type="file" name="csv_file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><button type="submit" onclick="return confirm('Proses upload data siswa dari CSV?')">Upload Data Massal <span>→</span></button></form>
</div>
</section>
<section class="panel" style="margin-bottom:20px"><div class="panel-heading"><div><h3><?= $edit?'Edit Siswa':'Tambah Siswa' ?></h3><p>Nama wajib diisi. NIS, NISN, rombel, dan jenis kelamin dapat dilengkapi.</p></div></div>
<form method="post" action="index.php" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end">
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
<label>NISN<input name="nisn" value="<?= h($edit['nisn']??'') ?>" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<label>NIS<input name="nis" value="<?= h($edit['nis']??'') ?>" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<label>Nama lengkap *<input required name="nama" value="<?= h($edit['nama']??'') ?>" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<label>Rombel<input name="rombel" placeholder="Contoh: 1A" value="<?= h($edit['rombel']??'') ?>" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<label>Jenis kelamin<select name="jenis_kelamin" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"><option value="">Pilih</option><option value="L" <?= ($edit['jenis_kelamin']??'')==='L'?'selected':'' ?>>Laki-laki</option><option value="P" <?= ($edit['jenis_kelamin']??'')==='P'?'selected':'' ?>>Perempuan</option></select></label>
<label>Status<select name="status" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"><option value="aktif" <?= ($edit['status']??'aktif')==='aktif'?'selected':'' ?>>Aktif</option><option value="nonaktif" <?= ($edit['status']??'')==='nonaktif'?'selected':'' ?>>Nonaktif</option></select></label>
<div style="display:flex;gap:8px"><button type="submit" style="padding:11px 16px;background:#4263eb;color:white;border:0;border-radius:8px;font-weight:700;cursor:pointer">Simpan</button><?php if($edit): ?><a href="index.php" style="padding:11px 16px;background:#edf1f7;border-radius:8px">Batal</a><?php endif; ?></div>
</form></section>
<section class="panel"><div class="panel-heading"><div><h3>Daftar Siswa</h3><p>Cari nama/NIS/NISN atau saring berdasarkan rombel.</p></div>
<form class="student-filter-form" method="get"><input name="q" placeholder="Cari siswa..." value="<?= h($q) ?>"><select name="rombel"><option value="">Semua rombel</option><?php foreach($rombels as $r): ?><option value="<?= h($r) ?>" <?= $rombelFilter===$r?'selected':'' ?>><?= h($r) ?></option><?php endforeach; ?></select><button type="submit">Filter</button></form></div>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><?php foreach(['Foto','NISN','NIS','Nama','Rombel','L/P','Status','Aksi'] as $heading): ?><th style="padding:12px;border-bottom:1px solid #e8edf4;color:#718096;font-size:12px"><?= h($heading) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php if(!$siswas): ?><tr><td colspan="8" style="padding:20px;color:#8290a5;text-align:center">Belum ada data siswa.</td></tr><?php endif; ?>
<?php foreach($siswas as $s): ?><tr><td style="padding:10px;border-bottom:1px solid #edf1f6;min-width:135px"><?php $photoUrl=''; foreach(['jpg','jpeg','png','webp'] as $photoExt){$photoFile=__DIR__.'/../../assets/images/siswa/'.(int)$s['id'].'.'.$photoExt;if(is_file($photoFile)){$photoUrl='../../assets/images/siswa/'.(int)$s['id'].'.'.$photoExt.'?v='.filemtime($photoFile);break;}} ?><div style="display:flex;align-items:center;gap:8px"><img src="<?= h($photoUrl ?: '../../assets/images/siswa/default-avatar.png') ?>" onerror="this.style.display='none'" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;background:#edf2f7"><form method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:4px"><input type="hidden" name="action" value="upload_photo"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required style="font-size:10px;max-width:150px"><button type="submit" style="border:0;border-radius:6px;background:#edf1ff;color:#3556cf;padding:4px 7px;font-size:11px;cursor:pointer">Upload</button></form></div></td><?php foreach(['nisn','nis','nama','rombel','jenis_kelamin','status'] as $col): ?><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($s[$col]??'-') ?></td><?php endforeach; ?><td style="padding:12px;border-bottom:1px solid #edf1f6;white-space:nowrap"><a href="index.php?edit=<?= (int)$s['id'] ?>" style="color:#4263eb;font-weight:700">Edit</a><form method="post" style="display:inline" onsubmit="return confirm('Hapus data siswa ini?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button style="margin-left:8px;border:0;background:none;color:#d34b4b;cursor:pointer">Hapus</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
