<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$pageTitle = 'Absensi';
if (!$pdo) {
    http_response_code(500);
    require __DIR__ . '/../../includes/header.php';
    echo '<div class="notice warning">Database belum tersambung. Periksa config/database.php.</div>';
    require __DIR__ . '/../../includes/footer.php';
    exit;
}
$errors = [];
$jenis = ($_REQUEST['jenis'] ?? 'siswa') === 'guru' ? 'guru' : 'siswa';
$tanggal = trim($_REQUEST['tanggal'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = date('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $jenis = ($_POST['jenis'] ?? 'siswa') === 'guru' ? 'guru' : 'siswa';
    $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = date('Y-m-d');
    $referensiId = (int)($_POST['referensi_id'] ?? 0);
    $status = $_POST['status'] ?? 'hadir';
    $allowed = ['hadir','izin','sakit','alpa','terlambat'];
    if (!in_array($status, $allowed, true)) $status = 'hadir';
    $waktu = trim($_POST['waktu'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $table = $jenis === 'guru' ? 'guru' : 'siswa';
    $check = $pdo->prepare("SELECT id FROM {$table} WHERE id=? AND status='aktif'");
    $check->execute([$referensiId]);
    if ($referensiId < 1 || !$check->fetch()) $errors[] = 'Pilih data orang yang aktif terlebih dahulu.';
    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO absensi (jenis,referensi_id,tanggal,waktu,status,keterangan) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE waktu=VALUES(waktu),status=VALUES(status),keterangan=VALUES(keterangan)');
        $stmt->execute([$jenis,$referensiId,$tanggal,$waktu ?: null,$status,$keterangan ?: null]);
        header('Location: index.php?saved=1&jenis='.urlencode($jenis).'&tanggal='.urlencode($tanggal)); exit;
    }
}
$personTable = $jenis === 'guru' ? 'guru' : 'siswa';
$personSql = $jenis === 'guru'
    ? "SELECT id,nama,COALESCE(nip,'') AS identitas,'' AS rombel FROM guru WHERE status='aktif' ORDER BY nama"
    : "SELECT id,nama,COALESCE(nis,'') AS identitas,COALESCE(rombel,'') AS rombel FROM siswa WHERE status='aktif' ORDER BY rombel,nama";
$people = $pdo->query($personSql)->fetchAll();
$q = $pdo->prepare('SELECT a.id,a.jenis,a.referensi_id,a.tanggal,a.waktu,a.status,a.keterangan, g.nama AS nama_guru, s.nama AS nama_siswa, s.rombel FROM absensi a LEFT JOIN guru g ON a.jenis="guru" AND a.referensi_id=g.id LEFT JOIN siswa s ON a.jenis="siswa" AND a.referensi_id=s.id WHERE a.tanggal=? AND a.jenis=? ORDER BY a.created_at DESC, a.id DESC');
$q->execute([$tanggal,$jenis]); $rows=$q->fetchAll();
$counts=['hadir'=>0,'izin'=>0,'sakit'=>0,'alpa'=>0,'terlambat'=>0];
foreach($rows as $row) if(isset($counts[$row['status']])) $counts[$row['status']]++;
require __DIR__ . '/../../includes/header.php';
?>
<section class="welcome"><div><span class="eyebrow">KEHADIRAN HARIAN</span><h2>Catat Absensi</h2><p>Input dan pantau kehadiran guru maupun siswa.</p></div><div class="date-chip"><?= h(date('d-m-Y',strtotime($tanggal))) ?></div></section>
<?php if(isset($_GET['saved'])): ?><div class="notice" style="background:#e8f8f1;color:#13845e">Absensi berhasil disimpan.</div><?php endif; ?>
<?php foreach($errors as $error): ?><div class="notice warning"><?= h($error) ?></div><?php endforeach; ?>
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));margin-bottom:20px">
<?php foreach(['hadir'=>'Hadir','izin'=>'Izin','sakit'=>'Sakit','alpa'=>'Alpa','terlambat'=>'Terlambat'] as $key=>$label): ?><article class="stat-card"><div class="stat-label"><?= h($label) ?></div><div class="stat-number"><?= (int)$counts[$key] ?></div><div class="stat-foot">Catatan tanggal ini</div></article><?php endforeach; ?>
</div>
<section class="panel" style="margin-bottom:20px"><div class="panel-heading"><div><h3>Input Kehadiran</h3><p>Satu orang hanya memiliki satu catatan per tanggal; penyimpanan ulang memperbarui catatan.</p></div></div>
<form method="post" action="index.php" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end">
<input type="hidden" name="action" value="save">
<label>Jenis absensi<select name="jenis" onchange="this.form.action='index.php';this.form.method='get';this.form.submit()" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"><option value="siswa" <?= $jenis==='siswa'?'selected':'' ?>>Siswa</option><option value="guru" <?= $jenis==='guru'?'selected':'' ?>>Guru</option></select></label>
<label>Tanggal<input type="date" name="tanggal" value="<?= h($tanggal) ?>" required style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<label>Nama <?= $jenis==='guru'?'guru':'siswa' ?><select name="referensi_id" required style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"><option value="">Pilih nama</option><?php foreach($people as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['nama']) ?><?= $p['rombel']!==''?' — '.h($p['rombel']):'' ?><?= $p['identitas']!==''?' ('.h($p['identitas']).')':'' ?></option><?php endforeach; ?></select></label>
<label>Status<select name="status" required style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"><?php foreach(['hadir'=>'Hadir','izin'=>'Izin','sakit'=>'Sakit','alpa'=>'Alpa','terlambat'=>'Terlambat'] as $val=>$label): ?><option value="<?= h($val) ?>"><?= h($label) ?></option><?php endforeach; ?></select></label>
<label>Waktu<input type="time" name="waktu" value="<?= h(date('H:i')) ?>" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<label>Keterangan<input name="keterangan" placeholder="Opsional" style="display:block;width:100%;padding:11px;border:1px solid #dce3ed;border-radius:8px;margin-top:6px"></label>
<div><button type="submit" style="padding:11px 16px;background:#4263eb;color:white;border:0;border-radius:8px;font-weight:700;cursor:pointer">Simpan Absensi</button></div>
</form>
<?php if(!$people): ?><p style="margin-top:14px;color:#b45309">Belum ada <?= $jenis==='guru'?'guru':'siswa' ?> aktif. Tambahkan data terlebih dahulu pada menu Data <?= $jenis==='guru'?'Guru':'Siswa' ?>.</p><?php endif; ?>
</section>
<section class="panel"><div class="panel-heading"><div><h3>Rekap Absensi</h3><p>Daftar catatan sesuai jenis dan tanggal yang dipilih.</p></div>
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap"><select name="jenis" style="padding:9px;border:1px solid #dce3ed;border-radius:8px"><option value="siswa" <?= $jenis==='siswa'?'selected':'' ?>>Siswa</option><option value="guru" <?= $jenis==='guru'?'selected':'' ?>>Guru</option></select><input type="date" name="tanggal" value="<?= h($tanggal) ?>" style="padding:9px;border:1px solid #dce3ed;border-radius:8px"><button style="padding:9px 13px;border:0;border-radius:8px;background:#edf1ff;color:#3556cf;cursor:pointer">Tampilkan</button><button type="button" onclick="window.print()" style="padding:9px 13px;border:0;border-radius:8px;background:#edf1f7;cursor:pointer">Cetak</button></form></div>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><?php foreach(['Nama','Rombel','Tanggal','Waktu','Status','Keterangan'] as $heading): ?><th style="padding:12px;border-bottom:1px solid #e8edf4;color:#718096;font-size:12px"><?= h($heading) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="6" style="padding:20px;color:#8290a5;text-align:center">Belum ada catatan absensi untuk pilihan ini.</td></tr><?php endif; ?>
<?php foreach($rows as $row): ?><tr><td style="padding:12px;border-bottom:1px solid #edf1f6;font-weight:600"><?= h($jenis==='guru'?$row['nama_guru']:$row['nama_siswa']) ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($row['rombel']??'-') ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($row['tanggal']) ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h(substr((string)$row['waktu'],0,5)?:'-') ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h(ucfirst($row['status'])) ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($row['keterangan']??'-') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<style>@media print{.sidebar,header nav,form,button{display:none!important}body{background:#fff}.panel,.stat-card{box-shadow:none;break-inside:avoid}}</style>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
