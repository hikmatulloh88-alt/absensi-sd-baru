<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$pageTitle = 'Rombongan Belajar - Ekskul';
$errors = [];
$success = '';
if (!$pdo) {
  http_response_code(500);
  require __DIR__ . '/../../includes/header.php';
  echo '<div class="notice warning">Database belum tersambung. Periksa config/database.php.</div>';
  require __DIR__ . '/../../includes/footer.php';
  exit;
}
$pdo->exec("CREATE TABLE IF NOT EXISTS ekskul (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL UNIQUE,
  pembina VARCHAR(150) NULL,
  jadwal VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS siswa_ekskul (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  siswa_id INT UNSIGNED NOT NULL,
  ekskul_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_siswa_ekskul (siswa_id, ekskul_id),
  INDEX idx_siswa_ekskul_ekskul (ekskul_id),
  CONSTRAINT fk_siswa_ekskul_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
  CONSTRAINT fk_siswa_ekskul_ekskul FOREIGN KEY (ekskul_id) REFERENCES ekskul(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add') {
    $nama = trim($_POST['nama'] ?? '');
    $pembina = trim($_POST['pembina'] ?? '');
    $jadwal = trim($_POST['jadwal'] ?? '');
    if ($nama === '') $errors[] = 'Nama ekstrakurikuler wajib diisi.';
    if (!$errors) {
      try {
        $stmt = $pdo->prepare('INSERT INTO ekskul (nama,pembina,jadwal) VALUES (?,?,?)');
        $stmt->execute([$nama,$pembina ?: null,$jadwal ?: null]);
        header('Location: index.php?saved=1'); exit;
      } catch (PDOException $e) { $errors[] = 'Nama ekskul sudah ada atau tidak dapat disimpan.'; }
    }
  } elseif ($action === 'assign') {
    $ekskulId = (int)($_POST['ekskul_id'] ?? 0);
    $siswaId = (int)($_POST['siswa_id'] ?? 0);
    $check = $pdo->prepare("SELECT id FROM siswa WHERE id=? AND status='aktif'");
    $check->execute([$siswaId]);
    $ek = $pdo->prepare('SELECT id FROM ekskul WHERE id=?');
    $ek->execute([$ekskulId]);
    if (!$check->fetchColumn() || !$ek->fetchColumn()) {
      $errors[] = 'Pilih siswa aktif dan ekskul yang valid.';
    } else {
      try {
        $stmt = $pdo->prepare('INSERT INTO siswa_ekskul (siswa_id,ekskul_id) VALUES (?,?)');
        $stmt->execute([$siswaId,$ekskulId]);
        header('Location: index.php?assigned=1&ekskul=' . $ekskulId); exit;
      } catch (PDOException $e) { $errors[] = 'Siswa sudah terdaftar pada ekskul ini.'; }
    }
  } elseif ($action === 'remove') {
    $ekskulId = (int)($_POST['ekskul_id'] ?? 0);
    $siswaId = (int)($_POST['siswa_id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM siswa_ekskul WHERE ekskul_id=? AND siswa_id=?');
    $stmt->execute([$ekskulId,$siswaId]);
    header('Location: index.php?removed=1&ekskul=' . $ekskulId); exit;
  } elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM ekskul WHERE id=?');
    $stmt->execute([$id]);
    header('Location: index.php?deleted=1'); exit;
  }
}
if (isset($_GET['saved'])) $success = 'Ekskul berhasil ditambahkan.';
if (isset($_GET['assigned'])) $success = 'Siswa berhasil didaftarkan ke ekskul.';
if (isset($_GET['removed'])) $success = 'Siswa dikeluarkan dari ekskul.';
if (isset($_GET['deleted'])) $success = 'Ekskul berhasil dihapus.';
$ekskuls = $pdo->query('SELECT e.id,e.nama,e.pembina,e.jadwal,COUNT(se.siswa_id) AS jumlah FROM ekskul e LEFT JOIN siswa_ekskul se ON se.ekskul_id=e.id GROUP BY e.id,e.nama,e.pembina,e.jadwal ORDER BY e.nama')->fetchAll();
$selectedId = (int)($_GET['ekskul'] ?? ($ekskuls[0]['id'] ?? 0));
$selected = null;
foreach ($ekskuls as $e) if ((int)$e['id'] === $selectedId) $selected = $e;
$students = [];
if ($selected) {
  $stmt = $pdo->prepare("SELECT s.id,s.nisn,s.nis,s.nama FROM siswa_ekskul se JOIN siswa s ON s.id=se.siswa_id WHERE se.ekskul_id=? AND s.status='aktif' ORDER BY s.nama");
  $stmt->execute([$selectedId]); $students = $stmt->fetchAll();
}
$available = [];
if ($selected) {
  $stmt = $pdo->prepare("SELECT s.id,s.nis,s.nisn,s.nama FROM siswa s WHERE s.status='aktif' AND NOT EXISTS (SELECT 1 FROM siswa_ekskul se WHERE se.siswa_id=s.id AND se.ekskul_id=?) ORDER BY s.nama LIMIT 500");
  $stmt->execute([$selectedId]); $available = $stmt->fetchAll();
}
$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE status='aktif'")->fetchColumn();
$totalMemberships = (int)$pdo->query('SELECT COUNT(*) FROM siswa_ekskul')->fetchColumn();
require __DIR__ . '/../../includes/header.php';
?>
<style>
.ek-page .ek-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}
.ek-page .ek-stat,.ek-page .ek-card{background:#fff;border:1px solid #e7edf5;border-radius:14px;padding:19px;box-shadow:0 5px 18px #243b5308;min-width:0}
.ek-page .ek-stat span{display:block;color:#75849a;font-size:12px;font-weight:600}.ek-page .ek-stat strong{display:block;font:800 26px Manrope,sans-serif;margin-top:7px}
.ek-page .ek-layout{display:grid;grid-template-columns:minmax(260px,.85fr) minmax(0,1.5fr);gap:18px}
.ek-page h3{margin:0 0 6px;font:800 16px Manrope,sans-serif}.ek-page p{color:#8290a5;font-size:12px;margin:0 0 16px}
.ek-page .ek-field{display:grid;gap:6px;margin-bottom:12px;font-size:12px;font-weight:600;color:#52627b}
.ek-page input,.ek-page select{width:100%;min-width:0;box-sizing:border-box;padding:10px 11px;border:1px solid #dce4ef;border-radius:8px;background:#fff;color:#19263d;font:13px 'DM Sans',sans-serif}
.ek-page .ek-btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:10px 13px;background:#4263eb;color:white;font-weight:700;font-size:12px;cursor:pointer}
.ek-page .ek-btn.danger{background:#fff0f0;color:#c43d4b}.ek-page .ek-list{display:grid;gap:7px;margin-top:14px}
.ek-page .ek-item{display:flex;align-items:center;gap:10px;border:1px solid #edf1f6;border-radius:10px;padding:11px 12px}
.ek-page .ek-item.selected{border-color:#b8c8ff;background:#f4f7ff}.ek-page .ek-item-main{min-width:0;flex:1}
.ek-page .ek-item-main a{display:block;font-weight:700;font-size:13px}.ek-page .ek-item-main small{display:block;margin-top:4px;color:#8290a5;font-size:11px}
.ek-page .ek-count{font-size:11px;background:#edf2ff;color:#4263eb;padding:6px 8px;border-radius:7px;white-space:nowrap}
.ek-page table{width:100%;border-collapse:collapse;text-align:left}.ek-page th{font-size:11px;color:#718096;background:#f5f8fc}.ek-page th,.ek-page td{padding:11px 10px;border-bottom:1px solid #edf1f6}.ek-page td{font-size:12px}
.ek-page .ek-toolbar{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
@media(max-width:850px){.ek-page .ek-layout{grid-template-columns:1fr}}@media(max-width:600px){.ek-page .ek-stats{grid-template-columns:1fr}.ek-page .ek-card{padding:15px}.ek-page .ek-item{flex-wrap:wrap}}
</style>
<div class="ek-page">
<section class="welcome"><div><span class="eyebrow">MASTER DATA</span><h2>Rombongan Belajar · Ekskul</h2><p>Kelola kegiatan ekstrakurikuler dan anggota siswa. Satu siswa dapat mengikuti beberapa ekskul.</p></div><div class="date-chip"><?= count($ekskuls) ?> ekskul</div></section>
<?php foreach($errors as $e): ?><div class="notice warning"><?= h($e) ?></div><?php endforeach; ?>
<?php if($success): ?><div class="notice" style="background:#e8f8f1;color:#13845e"><?= h($success) ?></div><?php endif; ?>
<div class="ek-stats"><div class="ek-stat"><span>Total Ekstrakurikuler</span><strong><?= count($ekskuls) ?></strong></div><div class="ek-stat"><span>Siswa Aktif</span><strong><?= $totalActive ?></strong></div><div class="ek-stat"><span>Total Keanggotaan</span><strong><?= $totalMemberships ?></strong></div></div>
<div class="ek-layout">
<section class="ek-card"><h3>Tambah Ekskul</h3><p>Contoh: Pramuka, Marching Band, Silat, atau Seni.</p>
<form method="post"><input type="hidden" name="action" value="add"><label class="ek-field">Nama Ekskul<input name="nama" maxlength="100" placeholder="Contoh: Pramuka" required></label><label class="ek-field">Nama Pembina<input name="pembina" maxlength="150" placeholder="Nama guru pembina"></label><label class="ek-field">Jadwal<input name="jadwal" maxlength="100" placeholder="Contoh: Jumat, 13.00"></label><button class="ek-btn" type="submit">＋ Tambah Ekskul</button></form>
<div class="ek-list"><h3 style="margin-top:18px">Daftar Ekskul</h3><?php if(!$ekskuls): ?><p>Belum ada ekskul. Tambahkan kegiatan terlebih dahulu.</p><?php endif; ?>
<?php foreach($ekskuls as $e): ?><div class="ek-item<?= $selectedId===(int)$e['id']?' selected':'' ?>"><div class="ek-item-main"><a href="index.php?ekskul=<?= (int)$e['id'] ?>"><?= h($e['nama']) ?></a><small><?= h($e['pembina'] ?: 'Pembina belum diisi') ?><?= $e['jadwal'] ? ' · '.h($e['jadwal']) : '' ?></small></div><span class="ek-count"><?= (int)$e['jumlah'] ?> siswa</span><form method="post" onsubmit="return confirm('Hapus ekskul ini beserta daftar anggotanya?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="ek-btn danger" type="submit" title="Hapus ekskul">×</button></form></div><?php endforeach; ?></div>
</section>
<section class="ek-card"><?php if($selected): ?><h3><?= h($selected['nama']) ?></h3><p><?= h($selected['pembina'] ?: 'Pembina belum ditentukan') ?><?= $selected['jadwal'] ? ' · '.h($selected['jadwal']) : '' ?></p>
<form method="post" class="ek-toolbar"><input type="hidden" name="action" value="assign"><input type="hidden" name="ekskul_id" value="<?= (int)$selected['id'] ?>"><select name="siswa_id" required style="flex:1;min-width:180px"><option value="">Pilih siswa aktif untuk didaftarkan</option><?php foreach($available as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['nama']) ?><?= $s['nis'] ? ' · '.h($s['nis']) : '' ?></option><?php endforeach; ?></select><button class="ek-btn" type="submit">＋ Daftarkan Siswa</button></form>
<?php else: ?><h3>Anggota Ekskul</h3><p>Pilih atau buat ekskul untuk mengelola anggota.</p><?php endif; ?>
<div style="overflow-x:auto"><table><thead><tr><th>No.</th><th>Nama Siswa</th><th>NISN / NIS</th><th>Aksi</th></tr></thead><tbody><?php if(!$students): ?><tr><td colspan="4" style="text-align:center;color:#8290a5;padding:24px">Belum ada anggota pada ekskul ini.</td></tr><?php endif; ?><?php foreach($students as $i=>$s): ?><tr><td><?= $i+1 ?></td><td><strong><?= h($s['nama']) ?></strong></td><td><?= h($s['nisn'] ?: '-') ?> / <?= h($s['nis'] ?: '-') ?></td><td><form method="post" onsubmit="return confirm('Keluarkan siswa dari ekskul ini?')"><input type="hidden" name="action" value="remove"><input type="hidden" name="ekskul_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="siswa_id" value="<?= (int)$s['id'] ?>"><button class="ek-btn danger" type="submit">Keluarkan</button></form></td></tr><?php endforeach; ?></tbody></table></div>
</section></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
