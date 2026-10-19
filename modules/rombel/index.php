<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$pageTitle = 'Rombongan Belajar';
$errors = [];
$success = '';
$pdo->exec("CREATE TABLE IF NOT EXISTS rombel (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(30) NOT NULL UNIQUE,
  tingkat TINYINT UNSIGNED NOT NULL,
  jenis ENUM('reguler','ekskul') NOT NULL DEFAULT 'reguler',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rombel_jenis_tingkat (jenis, tingkat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add') {
    $nama = trim($_POST['nama'] ?? '');
    $tingkat = (int)($_POST['tingkat'] ?? 0);
    if ($nama === '') $errors[] = 'Nama rombel wajib diisi.';
    if ($tingkat < 1 || $tingkat > 6) $errors[] = 'Tingkat harus antara kelas 1 sampai 6.';
    if (!$errors) {
      try {
        $stmt = $pdo->prepare("INSERT INTO rombel (nama, tingkat, jenis) VALUES (?, ?, 'reguler')");
        $stmt->execute([$nama, $tingkat]);
        header('Location: index.php?saved=1'); exit;
      } catch (PDOException $e) {
        $errors[] = 'Nama rombel sudah ada atau tidak dapat disimpan.';
      }
    }
  } elseif ($action === 'assign') {
    $studentId = (int)($_POST['siswa_id'] ?? 0);
    $rombelId = (int)($_POST['rombel_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT nama FROM rombel WHERE id=? AND jenis='reguler'");
    $stmt->execute([$rombelId]);
    $namaRombel = $stmt->fetchColumn();
    if ($studentId < 1 || !$namaRombel) {
      $errors[] = 'Pilih siswa dan rombel yang valid.';
    } else {
      $stmt = $pdo->prepare("UPDATE siswa SET rombel=? WHERE id=?");
      $stmt->execute([$namaRombel, $studentId]);
      header('Location: index.php?assigned=1&rombel=' . $rombelId); exit;
    }
  } elseif ($action === 'remove_student') {
    $studentId = (int)($_POST['siswa_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE siswa SET rombel=NULL WHERE id=?");
    $stmt->execute([$studentId]);
    header('Location: index.php?removed=1&rombel=' . (int)($_POST['rombel_id'] ?? 0)); exit;
  } elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT nama FROM rombel WHERE id=? AND jenis='reguler'");
    $stmt->execute([$id]);
    $nama = $stmt->fetchColumn();
    if ($nama) {
      $pdo->beginTransaction();
      try {
        $pdo->prepare("UPDATE siswa SET rombel=NULL WHERE rombel=?")->execute([$nama]);
        $pdo->prepare("DELETE FROM rombel WHERE id=?")->execute([$id]);
        $pdo->commit();
        header('Location: index.php?deleted=1'); exit;
      } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Rombel gagal dihapus.';
      }
    }
  }
}
if (isset($_GET['saved'])) $success = 'Rombongan belajar berhasil ditambahkan.';
if (isset($_GET['assigned'])) $success = 'Siswa berhasil dimasukkan ke rombel.';
if (isset($_GET['removed'])) $success = 'Siswa dikeluarkan dari rombel.';
if (isset($_GET['deleted'])) $success = 'Rombel berhasil dihapus.';
$rombels = $pdo->query("SELECT r.id,r.nama,r.tingkat,COUNT(s.id) AS jumlah FROM rombel r LEFT JOIN siswa s ON s.rombel=r.nama AND s.status='aktif' WHERE r.jenis='reguler' GROUP BY r.id,r.nama,r.tingkat ORDER BY r.tingkat,r.nama")->fetchAll();
$selectedId = (int)($_GET['rombel'] ?? ($rombels[0]['id'] ?? 0));
$selected = null;
foreach ($rombels as $r) if ((int)$r['id'] === $selectedId) $selected = $r;
$students = [];
if ($selected) {
  $stmt = $pdo->prepare("SELECT id,nisn,nis,nama,jenis_kelamin FROM siswa WHERE rombel=? AND status='aktif' ORDER BY nama");
  $stmt->execute([$selected['nama']]); $students = $stmt->fetchAll();
}
$unassigned = $pdo->query("SELECT id,nisn,nis,nama FROM siswa WHERE status='aktif' AND (rombel IS NULL OR rombel='') ORDER BY nama LIMIT 500")->fetchAll();
require __DIR__ . '/../../includes/header.php';
?>
<style>
.rb-page .rb-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}
.rb-page .rb-stat{padding:18px 20px;background:#fff;border:1px solid #e7edf5;border-radius:14px}
.rb-page .rb-stat span{display:block;color:#75849a;font-size:12px;font-weight:600}.rb-page .rb-stat strong{display:block;font:800 26px Manrope,sans-serif;margin-top:7px}
.rb-page .rb-layout{display:grid;grid-template-columns:minmax(260px,.85fr) minmax(0,1.5fr);gap:18px}
.rb-page .rb-card{background:#fff;border:1px solid #e7edf5;border-radius:14px;padding:20px;min-width:0;box-shadow:0 5px 18px #243b5308}
.rb-page .rb-card h3{margin:0 0 6px;font:800 16px Manrope,sans-serif}.rb-page .rb-card p{margin:0 0 16px;color:#8290a5;font-size:12px}
.rb-page .rb-field{display:grid;gap:6px;margin-bottom:12px;font-size:12px;font-weight:600;color:#52627b}
.rb-page input,.rb-page select{width:100%;min-width:0;padding:10px 11px;border:1px solid #dce4ef;border-radius:8px;background:#fff;color:#19263d;font:13px 'DM Sans',sans-serif}
.rb-page .rb-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:8px;padding:10px 13px;background:#4263eb;color:white;font-weight:700;font-size:12px;cursor:pointer}
.rb-page .rb-btn.secondary{background:#edf2ff;color:#4263eb}.rb-page .rb-btn.danger{background:#fff0f0;color:#c43d4b}
.rb-page .rb-list{display:grid;gap:7px;margin-top:14px}
.rb-page .rb-item{display:flex;align-items:center;gap:10px;border:1px solid #edf1f6;border-radius:10px;padding:11px 12px}
.rb-page .rb-item.selected{border-color:#b8c8ff;background:#f4f7ff}
.rb-page .rb-item-main{min-width:0;flex:1}.rb-page .rb-item-main a{display:block;font-weight:700;font-size:13px}.rb-page .rb-item-main small{display:block;margin-top:4px;color:#8290a5;font-size:11px}
.rb-page .rb-count{font-size:11px;background:#edf2ff;color:#4263eb;padding:6px 8px;border-radius:7px;white-space:nowrap}
.rb-page table{width:100%;border-collapse:collapse;text-align:left}.rb-page th{font-size:11px;color:#718096;background:#f5f8fc}.rb-page th,.rb-page td{padding:11px 10px;border-bottom:1px solid #edf1f6}.rb-page td{font-size:12px}
.rb-page .rb-toolbar{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
@media(max-width:850px){.rb-page .rb-layout{grid-template-columns:1fr}}
@media(max-width:600px){.rb-page .rb-stats{grid-template-columns:1fr}.rb-page .rb-card{padding:15px}.rb-page .rb-item{flex-wrap:wrap}}
</style>
<div class="rb-page">
<section class="welcome"><div><span class="eyebrow">MASTER DATA</span><h2>Rombongan Belajar · Reguler</h2><p>Kelola kelas dan penempatan siswa tanpa membuat data siswa baru.</p></div><div class="date-chip"><?= count($rombels) ?> rombel</div></section>
<?php foreach($errors as $e): ?><div class="notice warning"><?= h($e) ?></div><?php endforeach; ?>
<?php if($success): ?><div class="notice" style="background:#e8f8f1;color:#13845e"><?= h($success) ?></div><?php endif; ?>
<div class="rb-stats">
  <div class="rb-stat"><span>Total Rombel Reguler</span><strong><?= count($rombels) ?></strong></div>
  <div class="rb-stat"><span>Total Siswa Aktif</span><strong><?= (int)$pdo->query("SELECT COUNT(*) FROM siswa WHERE status='aktif'")->fetchColumn() ?></strong></div>
  <div class="rb-stat"><span>Belum Masuk Rombel</span><strong><?= count($unassigned) ?></strong></div>
</div>
<div class="rb-layout">
<section class="rb-card">
<h3>Tambah Rombel</h3><p>Buat kelas reguler baru, misalnya 1A, 2A, atau 6B.</p>
<form method="post"><input type="hidden" name="action" value="add">
<label class="rb-field">Nama Rombel<input name="nama" placeholder="Contoh: 1A" maxlength="30" required></label>
<label class="rb-field">Tingkat Kelas<select name="tingkat" required><?php for($i=1;$i<=6;$i++): ?><option value="<?= $i ?>">Kelas <?= $i ?></option><?php endfor; ?></select></label>
<button class="rb-btn" type="submit">＋ Tambah Rombel</button></form>
<div class="rb-list"><h3 style="margin-top:18px">Daftar Kelas</h3>
<?php if(!$rombels): ?><p>Belum ada rombel. Tambahkan kelas terlebih dahulu.</p><?php endif; ?>
<?php foreach($rombels as $r): ?><div class="rb-item<?= $selectedId===(int)$r['id']?' selected':'' ?>"><div class="rb-item-main"><a href="index.php?rombel=<?= (int)$r['id'] ?>"><?= h($r['nama']) ?></a><small>Kelas <?= (int)$r['tingkat'] ?></small></div><span class="rb-count"><?= (int)$r['jumlah'] ?> siswa</span><form method="post" onsubmit="return confirm('Hapus rombel ini? Siswa di dalamnya akan menjadi belum memiliki rombel.')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="rb-btn danger" type="submit" title="Hapus rombel">×</button></form></div><?php endforeach; ?>
</div>
</section>
<section class="rb-card">
<?php if($selected): ?>
<h3>Kelas <?= h($selected['nama']) ?></h3><p>Daftar siswa yang terhubung ke rombel ini.</p>
<form method="post" class="rb-toolbar"><input type="hidden" name="action" value="assign"><input type="hidden" name="rombel_id" value="<?= (int)$selected['id'] ?>"><select name="siswa_id" required style="flex:1;min-width:180px"><option value="">Pilih siswa yang belum memiliki rombel</option><?php foreach($unassigned as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['nama']) ?><?= $s['nis'] ? ' · '.h($s['nis']) : '' ?></option><?php endforeach; ?></select><button class="rb-btn" type="submit">＋ Masukkan Siswa</button></form>
<?php else: ?><h3>Daftar Anggota</h3><p>Pilih atau buat rombel untuk mulai menempatkan siswa.</p><?php endif; ?>
<div style="overflow-x:auto"><table><thead><tr><th>No.</th><th>Nama Siswa</th><th>NISN / NIS</th><th>Aksi</th></tr></thead><tbody>
<?php if(!$students): ?><tr><td colspan="4" style="text-align:center;color:#8290a5;padding:24px">Belum ada siswa pada rombel ini.</td></tr><?php endif; ?>
<?php foreach($students as $i=>$s): ?><tr><td><?= $i+1 ?></td><td><strong><?= h($s['nama']) ?></strong></td><td><?= h($s['nisn'] ?: '-') ?> / <?= h($s['nis'] ?: '-') ?></td><td><form method="post" onsubmit="return confirm('Keluarkan siswa dari rombel ini?')"><input type="hidden" name="action" value="remove_student"><input type="hidden" name="siswa_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="rombel_id" value="<?= (int)$selected['id'] ?>"><button class="rb-btn secondary" type="submit">Keluarkan</button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
</section>
</div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
