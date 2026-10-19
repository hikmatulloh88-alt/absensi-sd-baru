<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$pageTitle = 'Data Siswa';
$errors = [];
$edit = null;
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
?>
<section class="welcome"><div><span class="eyebrow">MASTER DATA</span><h2>Data Siswa</h2><p>Kelola identitas siswa dan rombongan belajar.</p></div><div class="date-chip"><?= count($siswas) ?> data siswa</div></section>
<?php if(isset($_GET['saved'])): ?><div class="notice" style="background:#e8f8f1;color:#13845e">Data siswa berhasil disimpan.</div><?php endif; ?>
<?php if(isset($_GET['deleted'])): ?><div class="notice" style="background:#e8f8f1;color:#13845e">Data siswa berhasil dihapus.</div><?php endif; ?>
<?php foreach($errors as $error): ?><div class="notice warning"><?= h($error) ?></div><?php endforeach; ?>
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
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><input name="q" placeholder="Cari siswa..." value="<?= h($q) ?>" style="padding:9px;border:1px solid #dce3ed;border-radius:8px"><select name="rombel" style="padding:9px;border:1px solid #dce3ed;border-radius:8px"><option value="">Semua rombel</option><?php foreach($rombels as $r): ?><option value="<?= h($r) ?>" <?= $rombelFilter===$r?'selected':'' ?>><?= h($r) ?></option><?php endforeach; ?></select><button style="padding:9px 13px;border:0;border-radius:8px;background:#edf1ff;color:#3556cf;cursor:pointer">Filter</button></form></div>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><?php foreach(['NISN','NIS','Nama','Rombel','L/P','Status','Aksi'] as $heading): ?><th style="padding:12px;border-bottom:1px solid #e8edf4;color:#718096;font-size:12px"><?= h($heading) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php if(!$siswas): ?><tr><td colspan="7" style="padding:20px;color:#8290a5;text-align:center">Belum ada data siswa.</td></tr><?php endif; ?>
<?php foreach($siswas as $s): ?><tr><?php foreach(['nisn','nis','nama','rombel','jenis_kelamin','status'] as $col): ?><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($s[$col]??'-') ?></td><?php endforeach; ?><td style="padding:12px;border-bottom:1px solid #edf1f6;white-space:nowrap"><a href="index.php?edit=<?= (int)$s['id'] ?>" style="color:#4263eb;font-weight:700">Edit</a><form method="post" style="display:inline" onsubmit="return confirm('Hapus data siswa ini?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button style="margin-left:8px;border:0;background:none;color:#d34b4b;cursor:pointer">Hapus</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
