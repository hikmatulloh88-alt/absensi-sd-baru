<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses khusus admin.'); }
$pageTitle='GTK Non-Aktif';
$q=trim((string)($_GET['q']??''));
$sql="SELECT id,nip,nama,jabatan,status FROM guru WHERE status='nonaktif'";
$args=[];
if($q!==''){ $sql.=" AND (nama LIKE ? OR nip LIKE ? OR jabatan LIKE ?)"; $like='%'.$q.'%'; $args=[$like,$like,$like]; }
$sql.=" ORDER BY nama";
$stmt=$pdo->prepare($sql); $stmt->execute($args); $rows=$stmt->fetchAll();
require __DIR__ . '/../../includes/header.php';
?>
<section class="welcome"><div><span class="eyebrow">GTK</span><h2>GTK Non-Aktif</h2><p>Data guru/GTK yang berstatus nonaktif. Data tetap tersimpan dan tidak dihapus.</p></div><div class="date-chip"><?= count($rows) ?> data</div></section>
<section class="panel"><div class="panel-heading"><div><h3>Daftar GTK Non-Aktif</h3><p>Perubahan status dilakukan dari menu Guru.</p></div><form method="get" style="display:flex;gap:8px"><input name="q" value="<?= h($q) ?>" placeholder="Cari nama/NIP/jabatan" style="padding:9px;border:1px solid #dce3ed;border-radius:8px"><button style="padding:9px 13px;border:0;border-radius:8px;background:#edf1ff;color:#3556cf">Cari</button></form></div>
<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><?php foreach(['NIP','Nama','Jabatan','Status','Aksi'] as $h): ?><th style="padding:12px;border-bottom:1px solid #e8edf4;color:#718096;font-size:12px"><?= h($h) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="5" style="padding:22px;text-align:center;color:#8290a5">Belum ada data GTK berstatus nonaktif.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($r['nip']??'-') ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6;font-weight:600"><?= h($r['nama']) ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($r['jabatan']??'-') ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><?= h($r['status']) ?></td><td style="padding:12px;border-bottom:1px solid #edf1f6"><a href="../guru/index.php?edit=<?= (int)$r['id'] ?>" style="color:#4263eb;font-weight:700">Ubah Status</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>