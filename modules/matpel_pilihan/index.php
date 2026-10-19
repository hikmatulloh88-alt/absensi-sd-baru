<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$pageTitle = 'Rombongan Belajar - Matpel Pilihan';
$errors = []; $success = '';
if (!$pdo) {
  http_response_code(500);
  require __DIR__ . '/../../includes/header.php';
  echo '<div class="notice warning">Database belum tersambung. Periksa config/database.php.</div>';
  require __DIR__ . '/../../includes/footer.php'; exit;
}
$pdo->exec("CREATE TABLE IF NOT EXISTS matpel_pilihan (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 nama VARCHAR(100) NOT NULL UNIQUE,
 tingkat TINYINT UNSIGNED NULL,
 keterangan VARCHAR(255) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS siswa_matpel_pilihan (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 siswa_id INT UNSIGNED NOT NULL,
 matpel_id INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_siswa_matpel_pilihan (siswa_id,matpel_id),
 INDEX idx_siswa_matpel (matpel_id),
 CONSTRAINT fk_smp_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
 CONSTRAINT fk_smp_matpel FOREIGN KEY (matpel_id) REFERENCES matpel_pilihan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 $action=$_POST['action']??'';
 if ($action==='add') {
  $nama=trim($_POST['nama']??''); $tingkat=(int)($_POST['tingkat']??0); $ket=trim($_POST['keterangan']??'');
  if ($nama==='') $errors[]='Nama mata pelajaran wajib diisi.';
  if ($tingkat!==0 && ($tingkat<1 || $tingkat>6)) $errors[]='Pilih tingkat kelas 1 sampai 6, atau semua tingkat.';
  if (!$errors) {
   try { $st=$pdo->prepare('INSERT INTO matpel_pilihan (nama,tingkat,keterangan) VALUES (?,?,?)'); $st->execute([$nama,$tingkat ?: null,$ket ?: null]); header('Location: index.php?saved=1'); exit; }
   catch(PDOException $e) { $errors[]='Nama mata pelajaran sudah ada atau tidak dapat disimpan.'; }
  }
 } elseif ($action==='assign') {
  $mid=(int)($_POST['matpel_id']??0); $sid=(int)($_POST['siswa_id']??0);
  $st=$pdo->prepare("SELECT id FROM siswa WHERE id=? AND status='aktif'"); $st->execute([$sid]); $validS=$st->fetchColumn();
  $st=$pdo->prepare('SELECT id,tingkat FROM matpel_pilihan WHERE id=?'); $st->execute([$mid]); $subject=$st->fetch();
  if (!$validS || !$subject) $errors[]='Pilih siswa aktif dan mata pelajaran yang valid.';
  else {
   if ($subject['tingkat']) { $st=$pdo->prepare("SELECT tingkat FROM rombel WHERE CONVERT(nama USING utf8mb4) COLLATE utf8mb4_unicode_ci = (SELECT CONVERT(rombel USING utf8mb4) COLLATE utf8mb4_unicode_ci FROM siswa WHERE id=?) AND jenis='reguler'"); $st->execute([$sid]); $grade=$st->fetchColumn(); if ($grade && (int)$grade !== (int)$subject['tingkat']) { $errors[]='Mata pelajaran ini khusus kelas '.$subject['tingkat'].'. Siswa tersebut terdaftar di tingkat berbeda.'; } }
   if (!$errors) { try { $st=$pdo->prepare('INSERT INTO siswa_matpel_pilihan (siswa_id,matpel_id) VALUES (?,?)'); $st->execute([$sid,$mid]); header('Location: index.php?assigned=1&matpel='.$mid); exit; } catch(PDOException $e) { $errors[]='Siswa sudah terdaftar pada mata pelajaran ini.'; } }
  }
 } elseif ($action==='remove') {
  $mid=(int)($_POST['matpel_id']??0); $sid=(int)($_POST['siswa_id']??0);
  $st=$pdo->prepare('DELETE FROM siswa_matpel_pilihan WHERE matpel_id=? AND siswa_id=?'); $st->execute([$mid,$sid]); header('Location: index.php?removed=1&matpel='.$mid); exit;
 } elseif ($action==='delete') {
  $st=$pdo->prepare('DELETE FROM matpel_pilihan WHERE id=?'); $st->execute([(int)($_POST['id']??0)]); header('Location: index.php?deleted=1'); exit;
 }
}
if(isset($_GET['saved']))$success='Mata pelajaran pilihan berhasil ditambahkan.';
if(isset($_GET['assigned']))$success='Siswa berhasil didaftarkan.';
if(isset($_GET['removed']))$success='Siswa dikeluarkan dari mata pelajaran.';
if(isset($_GET['deleted']))$success='Mata pelajaran pilihan berhasil dihapus.';
$subjects=$pdo->query('SELECT m.id,m.nama,m.tingkat,m.keterangan,COUNT(sm.siswa_id) jumlah FROM matpel_pilihan m LEFT JOIN siswa_matpel_pilihan sm ON sm.matpel_id=m.id GROUP BY m.id,m.nama,m.tingkat,m.keterangan ORDER BY m.tingkat,m.nama')->fetchAll();
$selectedId=(int)($_GET['matpel']??($subjects[0]['id']??0)); $selected=null;
foreach($subjects as $s)if((int)$s['id']===$selectedId)$selected=$s;
$students=[];$available=[];
if($selected){
 $st=$pdo->prepare("SELECT s.id,s.nisn,s.nis,s.nama,s.rombel FROM siswa_matpel_pilihan sm JOIN siswa s ON s.id=sm.siswa_id WHERE sm.matpel_id=? AND s.status='aktif' ORDER BY s.nama");$st->execute([$selectedId]);$students=$st->fetchAll();
 $sql="SELECT s.id,s.nisn,s.nis,s.nama,s.rombel FROM siswa s WHERE s.status='aktif' AND NOT EXISTS (SELECT 1 FROM siswa_matpel_pilihan sm WHERE sm.siswa_id=s.id AND sm.matpel_id=?)";
 $params=[$selectedId];
 if($selected['tingkat']){$sql.=" AND EXISTS (SELECT 1 FROM rombel r WHERE r.jenis='reguler' AND r.tingkat=? AND CONVERT(r.nama USING utf8mb4) COLLATE utf8mb4_unicode_ci=CONVERT(s.rombel USING utf8mb4) COLLATE utf8mb4_unicode_ci)";$params[]=(int)$selected['tingkat'];}
 $sql.=' ORDER BY s.nama LIMIT 500';$st=$pdo->prepare($sql);$st->execute($params);$available=$st->fetchAll();
}
$totalActive=(int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE status='aktif'")->fetchColumn();
$totalEnroll=(int)$pdo->query('SELECT COUNT(*) FROM siswa_matpel_pilihan')->fetchColumn();
require __DIR__ . '/../../includes/header.php';
?>
<style>
.mp-page .mp-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}.mp-page .mp-stat,.mp-page .mp-card{background:#fff;border:1px solid #e7edf5;border-radius:14px;padding:19px;box-shadow:0 5px 18px #243b5308;min-width:0}.mp-page .mp-stat span{display:block;color:#75849a;font-size:12px;font-weight:600}.mp-page .mp-stat strong{display:block;font:800 26px Manrope,sans-serif;margin-top:7px}.mp-page .mp-layout{display:grid;grid-template-columns:minmax(260px,.85fr) minmax(0,1.5fr);gap:18px}.mp-page h3{margin:0 0 6px;font:800 16px Manrope,sans-serif}.mp-page p{color:#8290a5;font-size:12px;margin:0 0 16px}.mp-page .mp-field{display:grid;gap:6px;margin-bottom:12px;font-size:12px;font-weight:600;color:#52627b}.mp-page input,.mp-page select{width:100%;min-width:0;box-sizing:border-box;padding:10px 11px;border:1px solid #dce4ef;border-radius:8px;background:#fff;color:#19263d;font:13px 'DM Sans',sans-serif}.mp-page .mp-btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:10px 13px;background:#4263eb;color:white;font-weight:700;font-size:12px;cursor:pointer}.mp-page .mp-btn.danger{background:#fff0f0;color:#c43d4b}.mp-page .mp-list{display:grid;gap:7px;margin-top:14px}.mp-page .mp-item{display:flex;align-items:center;gap:10px;border:1px solid #edf1f6;border-radius:10px;padding:11px 12px}.mp-page .mp-item.selected{border-color:#b8c8ff;background:#f4f7ff}.mp-page .mp-item-main{min-width:0;flex:1}.mp-page .mp-item-main a{display:block;font-weight:700;font-size:13px}.mp-page .mp-item-main small{display:block;margin-top:4px;color:#8290a5;font-size:11px}.mp-page .mp-count{font-size:11px;background:#edf2ff;color:#4263eb;padding:6px 8px;border-radius:7px;white-space:nowrap}.mp-page table{width:100%;border-collapse:collapse;text-align:left}.mp-page th{font-size:11px;color:#718096;background:#f5f8fc}.mp-page th,.mp-page td{padding:11px 10px;border-bottom:1px solid #edf1f6}.mp-page td{font-size:12px}.mp-page .mp-toolbar{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
@media(max-width:850px){.mp-page .mp-layout{grid-template-columns:1fr}}@media(max-width:600px){.mp-page .mp-stats{grid-template-columns:1fr}.mp-page .mp-card{padding:15px}.mp-page .mp-item{flex-wrap:wrap}}
</style>
<div class="mp-page">
<section class="welcome"><div><span class="eyebrow">MASTER DATA</span><h2>Rombongan Belajar · Matpel Pilihan</h2><p>Kelola mata pelajaran pilihan dan daftar siswa. Tingkat kelas dapat dibatasi jika diperlukan.</p></div><div class="date-chip"><?= count($subjects) ?> mata pelajaran</div></section>
<?php foreach($errors as $e): ?><div class="notice warning"><?= h($e) ?></div><?php endforeach; ?><?php if($success): ?><div class="notice" style="background:#e8f8f1;color:#13845e"><?= h($success) ?></div><?php endif; ?>
<div class="mp-stats"><div class="mp-stat"><span>Total Matpel Pilihan</span><strong><?= count($subjects) ?></strong></div><div class="mp-stat"><span>Siswa Aktif</span><strong><?= $totalActive ?></strong></div><div class="mp-stat"><span>Total Pendaftaran</span><strong><?= $totalEnroll ?></strong></div></div>
<div class="mp-layout"><section class="mp-card"><h3>Tambah Matpel Pilihan</h3><p>Isi nama pelajaran, tingkat sasaran, dan keterangan bila perlu.</p>
<form method="post"><input type="hidden" name="action" value="add"><label class="mp-field">Nama Mata Pelajaran<input name="nama" maxlength="100" placeholder="Contoh: Bahasa Inggris" required></label><label class="mp-field">Tingkat Kelas<select name="tingkat"><option value="0">Semua tingkat</option><?php for($i=1;$i<=6;$i++): ?><option value="<?= $i ?>">Kelas <?= $i ?></option><?php endfor; ?></select></label><label class="mp-field">Keterangan<input name="keterangan" maxlength="255" placeholder="Opsional"></label><button class="mp-btn" type="submit">＋ Tambah Matpel</button></form>
<div class="mp-list"><h3 style="margin-top:18px">Daftar Mata Pelajaran</h3><?php if(!$subjects): ?><p>Belum ada mata pelajaran pilihan.</p><?php endif; ?><?php foreach($subjects as $s): ?><div class="mp-item<?= $selectedId===(int)$s['id']?' selected':'' ?>"><div class="mp-item-main"><a href="index.php?matpel=<?= (int)$s['id'] ?>"><?= h($s['nama']) ?></a><small><?= $s['tingkat']?'Kelas '.(int)$s['tingkat']:'Semua tingkat' ?><?= $s['keterangan']?' · '.h($s['keterangan']):'' ?></small></div><span class="mp-count"><?= (int)$s['jumlah'] ?> siswa</span><form method="post" onsubmit="return confirm('Hapus mata pelajaran ini beserta pendaftarannya?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="mp-btn danger" type="submit">×</button></form></div><?php endforeach; ?></div></section>
<section class="mp-card"><?php if($selected): ?><h3><?= h($selected['nama']) ?></h3><p><?= $selected['tingkat']?'Khusus kelas '.(int)$selected['tingkat']:'Terbuka untuk semua tingkat' ?></p><form method="post" class="mp-toolbar"><input type="hidden" name="action" value="assign"><input type="hidden" name="matpel_id" value="<?= (int)$selected['id'] ?>"><select name="siswa_id" required style="flex:1;min-width:180px"><option value="">Pilih siswa aktif</option><?php foreach($available as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['nama']) ?><?= $s['rombel']?' · '.h($s['rombel']):'' ?></option><?php endforeach; ?></select><button class="mp-btn" type="submit">＋ Daftarkan Siswa</button></form><?php else: ?><h3>Daftar Siswa</h3><p>Pilih atau buat mata pelajaran untuk mengelola pendaftaran.</p><?php endif; ?>
<div style="overflow-x:auto"><table><thead><tr><th>No.</th><th>Nama Siswa</th><th>Rombel</th><th>NISN / NIS</th><th>Aksi</th></tr></thead><tbody><?php if(!$students): ?><tr><td colspan="5" style="text-align:center;color:#8290a5;padding:24px">Belum ada siswa terdaftar.</td></tr><?php endif; ?><?php foreach($students as $i=>$s): ?><tr><td><?= $i+1 ?></td><td><strong><?= h($s['nama']) ?></strong></td><td><?= h($s['rombel']?:'-') ?></td><td><?= h($s['nisn']?:'-') ?> / <?= h($s['nis']?:'-') ?></td><td><form method="post" onsubmit="return confirm('Keluarkan siswa dari mata pelajaran ini?')"><input type="hidden" name="action" value="remove"><input type="hidden" name="matpel_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="siswa_id" value="<?= (int)$s['id'] ?>"><button class="mp-btn danger" type="submit">Keluarkan</button></form></td></tr><?php endforeach; ?></tbody></table></div></section></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
