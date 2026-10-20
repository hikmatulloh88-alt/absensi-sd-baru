<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses khusus admin.'); }
$pageTitle='PD Keluar';
$q=trim((string)($_GET['q']??''));
$sql="SELECT id,nisn,nis,nama,rombel,status FROM siswa WHERE status='nonaktif'";
$args=[];
if($q!==''){ $sql.=" AND (nama LIKE ? OR nis LIKE ? OR nisn LIKE ? OR rombel LIKE ?)"; $like='%'.$q.'%'; $args=[$like,$like,$like,$like]; }
$sql.=" ORDER BY rombel,nama";
$stmt=$pdo->prepare($sql); $stmt->execute($args); $rows=$stmt->fetchAll();
require __DIR__ . '/../../includes/header.php';
?>
<section class="welcome"><div><span class="eyebrow">PESERTA DIDIK</span><h2>Peserta Didik Keluar</h2><p>Daftar siswa berstatus nonaktif. Data tetap tersimpan di database.</p></div><div class="date-chip"><?= count($rows) ?> data</div></section>
<section class="panel"><div class="panel-heading"><div><h3>Daftar PD Keluar</h3><p>Periksa status siswa melalui menu Peserta Didik; tidak ada data yang dihapus.</p></div><form method="get" style="display:flex;gap:8px"><input name="q" value="<?= h($q) ?>" placeholder="Cari nama/NIS/NISN/rombel" style="padding:9px;border:1px solid #dce3ed;border-radius:8px"><button style="padding:9px 13px;border:0;border-radius:8px;background:#edf1ff;color:#3556cf">Cari</button></form></div>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><?php foreach(['NISN','NIS','Nama','Rombel','Status','Aksi'] as $h): ?><th style="padding:12px;border-bottom:1px solid #e8edf4;color:#718096;font-size:12px"><?= h($h) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="6" style="padding:22px;text-align:center;color:#8290a5">Belum ada siswa berstatus nonaktif.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><?php foreach(['nisn','nis','nama','rombel','status'] as $col): ?><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($r[$col]??'-') ?></td><?php endforeach; ?><td style="padding:12px;border-bottom:1px solid #edf1f6"><a href="../siswa/index.php?edit=<?= (int)$r['id'] ?>" style="color:#4263eb;font-weight:700">Lihat/Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>