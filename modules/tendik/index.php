<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses khusus admin.'); }
$pageTitle='Tenaga Kependidikan';
$rows=[]; $warning='';
try {
  $stmt=$pdo->query("SELECT id,nip,nama,jabatan,status FROM guru WHERE LOWER(COALESCE(jabatan,'')) LIKE '%tendik%' OR LOWER(COALESCE(jabatan,'')) LIKE '%operator%' OR LOWER(COALESCE(jabatan,'')) LIKE '%staf%' OR LOWER(COALESCE(jabatan,'')) LIKE '%administrasi%' OR LOWER(COALESCE(jabatan,'')) LIKE '%perpustakaan%' OR LOWER(COALESCE(jabatan,'')) LIKE '%penjaga%' ORDER BY nama");
  $rows=$stmt->fetchAll();
} catch(Throwable $e) { $warning='Data belum dapat dibaca dari tabel guru.'; }
require __DIR__ . '/../../includes/header.php';
?>
<section class="welcome"><div><span class="eyebrow">GTK</span><h2>Tenaga Kependidikan</h2><p>Daftar tenaga kependidikan berdasarkan jabatan pada data Guru yang sudah tersimpan.</p></div><div class="date-chip"><?= count($rows) ?> data</div></section>
<?php if($warning): ?><div class="notice warning"><?= h($warning) ?></div><?php endif; ?>
<section class="panel"><div class="panel-heading"><div><h3>Daftar Tendik</h3><p>Untuk menambah atau mengubah data, gunakan menu Guru agar sumber data tetap satu.</p></div><a href="../guru/index.php" style="padding:10px 14px;background:#edf1ff;color:#3556cf;border-radius:8px;font-weight:700;text-decoration:none">Kelola Data Guru</a></div>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><?php foreach(['NIP','Nama','Jabatan','Status'] as $h): ?><th style="padding:12px;border-bottom:1px solid #e8edf4;color:#718096;font-size:12px"><?= h($h) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="4" style="padding:22px;text-align:center;color:#8290a5">Belum ada data dengan jabatan Tendik, Operator, Staf, Administrasi, Perpustakaan, atau Penjaga. Data guru tidak diubah.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><?php foreach(['nip','nama','jabatan','status'] as $col): ?><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($r[$col]??'-') ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>