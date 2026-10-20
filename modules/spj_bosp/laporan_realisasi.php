<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses laporan SPJ hanya untuk admin.'); }
$pageTitle='Laporan Realisasi SPJ';
$year=(int)($_GET['tahun']??date('Y'));$source=(string)($_GET['sumber']??'BOSP');$month=(int)($_GET['bulan']??0);
if($year<2000||$year>2100)$year=(int)date('Y');
if(!in_array($source,['BOSP','BOS_KINERJA'],true))$source='BOSP';
if($month<0||$month>12)$month=0;
$errors=[];$rows=[];$monthly=[];$total=0;$tax=0;$unmapped=0;$ready=false;
try{$pdo->query("SELECT 1 FROM spj_transaksi_rkas LIMIT 1");$ready=true;}catch(Throwable $e){}
if(!$ready)$errors[]='Tabel pemetaan belum tersedia. Jalankan migrasi database/spj_rencana_bulanan.sql pada database uji.';
if($ready)try{
 $sql="SELECT t.id,t.tanggal,t.nomor_bukti,t.kegiatan,t.uraian,t.nilai_bruto,t.pajak,k.kode_kegiatan,k.kode_rekening,k.uraian AS rkas_uraian FROM spj_transaksi t LEFT JOIN spj_transaksi_rkas m ON m.transaksi_id=t.id LEFT JOIN spj_rkas_kegiatan k ON k.id=m.kegiatan_id WHERE t.sumber_dana=? AND YEAR(t.tanggal)=?";
 $args=[$source,$year];if($month){$sql.=" AND MONTH(t.tanggal)=?";$args[]=$month;}$sql.=" ORDER BY t.tanggal,t.id";
 $q=$pdo->prepare($sql);$q->execute($args);$rows=$q->fetchAll();
 foreach($rows as $r){$total+=(float)$r['nilai_bruto'];$tax+=(float)$r['pajak'];if(empty($r['rkas_uraian']))$unmapped++;$m=(int)substr($r['tanggal'],5,2);if(!isset($monthly[$m]))$monthly[$m]=['count'=>0,'gross'=>0,'tax'=>0,'unmapped'=>0];$monthly[$m]['count']++;$monthly[$m]['gross']+=(float)$r['nilai_bruto'];$monthly[$m]['tax']+=(float)$r['pajak'];if(empty($r['rkas_uraian']))$monthly[$m]['unmapped']++;}
}catch(Throwable $e){$errors[]='Laporan gagal dibaca.';}
require __DIR__.'/../../includes/header.php';
?>
<style>
.rep-wrap{max-width:1200px;margin:auto}.rep-panel{background:var(--card,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px;padding:16px;margin-bottom:16px}.rep-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.rep-field label{display:block;font-size:13px;margin-bottom:6px;color:var(--text-muted,#64748b)}.rep-field input,.rep-field select{width:100%;padding:10px;border:1px solid var(--border,#d1d5db);border-radius:8px;background:var(--card,#fff);color:inherit}.rep-btn{border:0;border-radius:8px;padding:10px 14px;background:var(--primary,#2563eb);color:#fff;cursor:pointer}.rep-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.rep-stat{border:1px solid var(--border,#e5e7eb);border-radius:10px;padding:12px}.rep-stat small{color:var(--text-muted,#64748b)}.rep-stat strong{display:block;margin-top:6px}.rep-table-wrap{overflow:auto}.rep-table{width:100%;border-collapse:collapse;min-width:850px}.rep-table th,.rep-table td{padding:9px;border-bottom:1px solid var(--border,#e5e7eb);font-size:12px;text-align:left}.rep-table th{background:var(--surface,#f8fafc)}.rep-alert{background:#fef2f2;color:#991b1b;padding:12px;border-radius:9px;margin-bottom:10px}.muted{font-size:13px;color:var(--text-muted,#64748b)}@media(max-width:800px){.rep-grid,.rep-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:480px){.rep-grid,.rep-stats{grid-template-columns:1fr}}
</style>
<main class="main-content"><div class="rep-wrap"><div class="page-header"><div><h1>Laporan Realisasi SPJ</h1><p class="muted">Ringkasan transaksi bruto, pajak yang sudah tercatat di dalam bruto, dan status pemetaan RKAS.</p></div><button class="rep-btn" onclick="window.print()">Cetak laporan</button></div>
<?php foreach($errors as $e): ?><div class="rep-alert"><?= h($e) ?></div><?php endforeach; ?>
<div class="rep-panel"><form method="get" class="rep-grid"><div class="rep-field"><label>Tahun</label><input type="number" name="tahun" min="2000" max="2100" value="<?= h($year) ?>"></div><div class="rep-field"><label>Sumber dana</label><select name="sumber"><option value="BOSP" <?= $source==='BOSP'?'selected':'' ?>>BOSP Reguler</option><option value="BOS_KINERJA" <?= $source==='BOS_KINERJA'?'selected':'' ?>>BOS Kinerja</option></select></div><div class="rep-field"><label>Bulan (opsional)</label><select name="bulan"><option value="0">Semua bulan</option><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= h(date('F',mktime(0,0,0,$m,1))) ?></option><?php endfor; ?></select></div><div><button class="rep-btn">Tampilkan laporan</button></div></form></div>
<div class="rep-panel"><div class="rep-stats"><div class="rep-stat"><small>Total transaksi bruto</small><strong>Rp <?= number_format($total,0,',','.') ?></strong></div><div class="rep-stat"><small>Pajak tercatat (bagian bruto)</small><strong>Rp <?= number_format($tax,0,',','.') ?></strong></div><div class="rep-stat"><small>Jumlah transaksi</small><strong><?= count($rows) ?></strong></div><div class="rep-stat"><small>Belum dipetakan</small><strong><?= $unmapped ?></strong></div></div><p class="muted">Nilai bruto tidak ditambah pajak lagi. Laporan ini memakai transaksi yang tersimpan di SPJ, bukan rekonsiliasi lengkap BKU.</p></div>
<div class="rep-panel"><h2>Ringkasan per bulan</h2><div class="rep-table-wrap"><table class="rep-table"><thead><tr><th>Bulan</th><th>Transaksi</th><th>Bruto</th><th>Pajak tercatat</th><th>Belum dipetakan</th></tr></thead><tbody>
<?php for($m=1;$m<=12;$m++): if($month&&$month!==$m)continue; $v=$monthly[$m]??['count'=>0,'gross'=>0,'tax'=>0,'unmapped'=>0]; ?><tr><td><?= h(date('F',mktime(0,0,0,$m,1))) ?></td><td><?= $v['count'] ?></td><td>Rp <?= number_format($v['gross'],0,',','.') ?></td><td>Rp <?= number_format($v['tax'],0,',','.') ?></td><td><?= $v['unmapped'] ?></td></tr><?php endfor; ?>
</tbody></table></div></div>
<div class="rep-panel"><h2>Rincian transaksi</h2><div class="rep-table-wrap"><table class="rep-table"><thead><tr><th>Tanggal</th><th>No. bukti</th><th>Uraian SPJ</th><th>Kegiatan RKAS</th><th>Bruto</th><th>Pajak</th><th>Status</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="7">Tidak ada transaksi untuk filter ini.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><?= h($r['tanggal']) ?></td><td><?= h($r['nomor_bukti']?:'-') ?></td><td><?= h($r['uraian']) ?></td><td><?= h($r['rkas_uraian']??'') ?: '—' ?></td><td>Rp <?= number_format((float)$r['nilai_bruto'],0,',','.') ?></td><td>Rp <?= number_format((float)$r['pajak'],0,',','.') ?></td><td><?= !empty($r['rkas_uraian'])?'Terpetakan':'Belum dipetakan' ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</div></main><?php require __DIR__.'/../../includes/footer.php'; ?>
