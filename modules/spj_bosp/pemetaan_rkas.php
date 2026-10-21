<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses pemetaan SPJ hanya untuk admin.'); }
$pageTitle='Pemetaan Realisasi RKAS'; $errors=[]; $ok='';
$year=(int)($_GET['tahun']??date('Y')); $source=(string)($_GET['sumber']??'BOSP');
if($year<2000||$year>2100)$year=(int)date('Y');
if(!in_array($source,['BOSP','BOS_KINERJA'],true))$source='BOSP';
if(empty($_SESSION['spj_csrf']))$_SESSION['spj_csrf']=bin2hex(random_bytes(32));
$ready=false;
try{$pdo->query("SELECT 1 FROM spj_transaksi_rkas LIMIT 1");$pdo->query("SELECT 1 FROM spj_rkas_kegiatan LIMIT 1");$ready=true;}catch(Throwable $e){}
if(!$ready)$errors[]='Jalankan migrasi database/spj_rencana_bulanan.sql pada database uji setelah backup.';
if($ready&&$_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals((string)$_SESSION['spj_csrf'],(string)($_POST['csrf']??'')))$errors[]='Sesi formulir berakhir. Muat ulang halaman.';
 else{
  $year=(int)($_POST['tahun']??$year);$source=(string)($_POST['sumber']??$source);
  $tid=filter_var($_POST['transaksi_id']??null,FILTER_VALIDATE_INT);$kidRaw=trim((string)($_POST['kegiatan_id']??''));$kid=$kidRaw===''?0:filter_var($kidRaw,FILTER_VALIDATE_INT);
  if($year<2000||$year>2100||!in_array($source,['BOSP','BOS_KINERJA'],true)||!$tid||$kid===false)$errors[]='Data pemetaan tidak valid.';
  else try{
   $pdo->beginTransaction();
   $q=$pdo->prepare("SELECT id FROM spj_transaksi WHERE id=? AND sumber_dana=? AND YEAR(tanggal)=? FOR UPDATE");$q->execute([$tid,$source,$year]);$tx=$q->fetchColumn();
   $q=$pdo->prepare("SELECT k.id FROM spj_rkas_kegiatan k JOIN spj_rkas_import i ON i.id=k.import_id WHERE k.id=? AND i.tahun_anggaran=? AND i.sumber_dana=? AND i.status='aktif' FOR UPDATE");$q->execute([$kid,$year,$source]);$act=$q->fetchColumn();
   if(!$tx)throw new RuntimeException('Transaksi tidak sesuai tahun/sumber dana yang dipilih.');
   if(!$kid){$q=$pdo->prepare("DELETE FROM spj_transaksi_rkas WHERE transaksi_id=?");$q->execute([$tid]);$pdo->commit();$ok='Pemetaan transaksi dihapus; transaksi kembali berstatus belum dipetakan.';}
   else{
    if(!$act)throw new RuntimeException('Kegiatan RKAS harus sesuai tahun/sumber dana yang dipilih.');
    $q=$pdo->prepare("INSERT INTO spj_transaksi_rkas(transaksi_id,kegiatan_id,catatan,mapped_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE kegiatan_id=VALUES(kegiatan_id),catatan=VALUES(catatan),mapped_by=VALUES(mapped_by),mapped_at=CURRENT_TIMESTAMP");
    $q->execute([$tid,$kid,trim((string)($_POST['catatan']??''))?:null,$_SESSION['akun_id']??null]);
    $pdo->commit();$ok='Pemetaan transaksi tersimpan.';
   }
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors[]=$e instanceof RuntimeException?$e->getMessage():'Pemetaan gagal disimpan.';}
 }
}
$activities=[];$transactions=[];$totals=[];$summary=['count'=>0,'gross'=>0.0,'unmapped_count'=>0,'unmapped_gross'=>0.0];
if($ready)try{
 $q=$pdo->prepare("SELECT k.id,k.kode_kegiatan,k.kode_rekening,k.uraian,k.pagu FROM spj_rkas_kegiatan k JOIN spj_rkas_import i ON i.id=k.import_id WHERE i.tahun_anggaran=? AND i.sumber_dana=? AND i.status='aktif' ORDER BY k.uraian");$q->execute([$year,$source]);$activities=$q->fetchAll();
 $q=$pdo->prepare("SELECT COUNT(*) jumlah,COALESCE(SUM(t.nilai_bruto),0) bruto,COALESCE(SUM(CASE WHEN m.transaksi_id IS NULL THEN 1 ELSE 0 END),0) belum_jumlah,COALESCE(SUM(CASE WHEN m.transaksi_id IS NULL THEN t.nilai_bruto ELSE 0 END),0) belum_bruto FROM spj_transaksi t LEFT JOIN spj_transaksi_rkas m ON m.transaksi_id=t.id WHERE t.sumber_dana=? AND YEAR(t.tanggal)=?");$q->execute([$source,$year]);$s=$q->fetch();if($s)$summary=['count'=>(int)$s['jumlah'],'gross'=>(float)$s['bruto'],'unmapped_count'=>(int)$s['belum_jumlah'],'unmapped_gross'=>(float)$s['belum_bruto']];
 $q=$pdo->prepare("SELECT t.id,t.tanggal,t.nomor_bukti,t.kegiatan,t.uraian,t.nilai_bruto,t.pajak,m.kegiatan_id FROM spj_transaksi t LEFT JOIN spj_transaksi_rkas m ON m.transaksi_id=t.id WHERE t.sumber_dana=? AND YEAR(t.tanggal)=? ORDER BY (m.transaksi_id IS NULL) DESC,t.tanggal,t.id LIMIT 500");$q->execute([$source,$year]);$transactions=$q->fetchAll();
 $q=$pdo->prepare("SELECT m.kegiatan_id,SUM(t.nilai_bruto) realisasi,SUM(t.pajak) pajak FROM spj_transaksi_rkas m JOIN spj_transaksi t ON t.id=m.transaksi_id WHERE t.sumber_dana=? AND YEAR(t.tanggal)=? GROUP BY m.kegiatan_id");$q->execute([$source,$year]);foreach($q->fetchAll() as $v)$totals[(int)$v['kegiatan_id']]=['realisasi'=>(float)$v['realisasi'],'pajak'=>(float)$v['pajak']];
}catch(Throwable $e){$errors[]='Data pemetaan gagal dibaca.';}
require __DIR__.'/../../includes/header.php';
?>
<style>
.map-wrap{max-width:1200px;margin:auto}.map-panel{background:var(--card,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px;padding:16px;margin:0 0 16px}.map-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.map-grid label{display:block;font-size:13px;margin-bottom:6px}.map-grid input,.map-grid select{width:100%;padding:9px;border:1px solid var(--border,#d1d5db);border-radius:8px;background:var(--card,#fff);color:inherit}.map-btn{border:0;border-radius:8px;padding:9px 12px;background:var(--primary,#2563eb);color:#fff;cursor:pointer}.map-table-wrap{overflow:auto}.map-table{width:100%;border-collapse:collapse;min-width:900px}.map-table th,.map-table td{padding:9px;border-bottom:1px solid var(--border,#e5e7eb);font-size:12px;text-align:left;vertical-align:top}.map-table th{background:var(--surface,#f8fafc)}.map-alert,.map-ok{padding:12px;border-radius:9px;margin:10px 0}.map-alert{background:#fef2f2;color:#991b1b}.map-ok{background:#ecfdf5;color:#065f46}.muted{font-size:13px;color:var(--text-muted,#64748b)}@media(max-width:700px){.map-grid{grid-template-columns:1fr}.map-panel{padding:12px}}
</style>
<div class="main-content"><div class="map-wrap"><div class="page-header"><div><h1>Pemetaan Realisasi ke RKAS</h1><p class="muted">Pilih kegiatan RKAS secara manual. Transaksi tidak dipetakan otomatis berdasarkan tebakan uraian.</p></div></div>
<?php foreach($errors as $e): ?><div class="map-alert"><?= h($e) ?></div><?php endforeach; ?><?php if($ok): ?><div class="map-ok"><?= h($ok) ?></div><?php endif; ?>
<div class="map-panel"><form method="get" class="map-grid"><div><label>Tahun</label><input type="number" name="tahun" min="2000" max="2100" value="<?= h($year) ?>"></div><div><label>Sumber dana</label><select name="sumber"><option value="BOSP" <?= $source==='BOSP'?'selected':'' ?>>BOSP Reguler</option><option value="BOS_KINERJA" <?= $source==='BOS_KINERJA'?'selected':'' ?>>BOS Kinerja</option></select></div><div><button class="map-btn">Tampilkan</button></div></form></div>
<div class="map-panel"><h2>Ringkasan pemetaan</h2><div class="map-grid"><div><strong>Total transaksi</strong><br><?= number_format($summary['count'],0,',','.') ?> · Rp <?= number_format($summary['gross'],0,',','.') ?></div><div><strong>Belum dipetakan</strong><br><?= number_format($summary['unmapped_count'],0,',','.') ?> · Rp <?= number_format($summary['unmapped_gross'],0,',','.') ?></div></div><p class="muted">Daftar di bawah menampilkan maksimal 500 transaksi. Ringkasan menghitung semua transaksi pada tahun dan sumber dana terpilih.</p></div><div class="map-panel"><h2>Realisasi yang sudah dipetakan</h2><div class="map-table-wrap"><table class="map-table"><thead><tr><th>Kode / kegiatan</th><th>Pagu</th><th>Realisasi bruto terpetakan</th><th>Pajak dalam bruto</th><th>Sisa indikatif</th><th>Status pagu</th></tr></thead><tbody>
<?php if(!$activities): ?><tr><td colspan="6">Belum ada RKAS aktif untuk pilihan ini.</td></tr><?php endif; ?>
<?php foreach($activities as $a): $v=$totals[(int)$a['id']]??['realisasi'=>0,'pajak'=>0]; ?><tr><td><?= h($a['kode_kegiatan']?:'-') ?> · <?= h($a['kode_rekening']?:'-') ?><br><?= h($a['uraian']) ?></td><td>Rp <?= number_format((float)$a['pagu'],0,',','.') ?></td><td>Rp <?= number_format($v['realisasi'],0,',','.') ?></td><td>Rp <?= number_format($v['pajak'],0,',','.') ?></td><td style="color:<?= $v['realisasi']>(float)$a['pagu']?'#b91c1c':'inherit' ?>">Rp <?= number_format((float)$a['pagu']-$v['realisasi'],0,',','.') ?></td><td><?php if($v['realisasi']>(float)$a['pagu']+0.005): ?><strong style="color:#b91c1c">Melebihi pagu</strong><?php elseif(abs((float)$v['realisasi']-(float)$a['pagu'])<0.005): ?><strong>Pagu terserap 100%</strong><?php else: ?>Dalam pagu<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><p class="muted">Realisasi hanya mencakup transaksi yang sudah dipetakan. Sisa indikatif belum merupakan saldo resmi dan belum memperhitungkan rencana bulanan.</p></div>
<div class="map-panel"><h2>Pemetaan transaksi tahun <?= h($year) ?></h2><div class="map-table-wrap"><table class="map-table"><thead><tr><th>Tanggal / bukti</th><th>Uraian transaksi</th><th>Nominal bruto</th><th>Pilih kegiatan RKAS</th></tr></thead><tbody>
<?php if(!$transactions): ?><tr><td colspan="4">Belum ada transaksi untuk tahun dan sumber dana ini.</td></tr><?php endif; ?>
<?php foreach($transactions as $t): ?><tr><td><?= h($t['tanggal']) ?><br><?= h($t['nomor_bukti']?:'-') ?></td><td><strong><?= h($t['uraian']) ?></strong><br><span class="muted"><?= h($t['kegiatan']) ?></span></td><td>Rp <?= number_format((float)$t['nilai_bruto'],0,',','.') ?><br><span class="muted">Pajak: Rp <?= number_format((float)$t['pajak'],0,',','.') ?></span></td><td><?php if($activities): ?><form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['spj_csrf']) ?>"><input type="hidden" name="tahun" value="<?= h($year) ?>"><input type="hidden" name="sumber" value="<?= h($source) ?>"><input type="hidden" name="transaksi_id" value="<?= (int)$t['id'] ?>"><select name="kegiatan_id" style="width:100%;padding:8px"><option value="">— Belum dipetakan / hapus pemetaan —</option><?php foreach($activities as $a): ?><option value="<?= (int)$a['id'] ?>" <?= ((int)($t['kegiatan_id']??0)===(int)$a['id'])?'selected':'' ?>><?= h(($a['kode_kegiatan']?:'-').' · '.$a['uraian']) ?></option><?php endforeach; ?></select><input name="catatan" placeholder="Catatan opsional" style="width:100%;padding:8px;margin-top:6px"><button class="map-btn" style="margin-top:6px" type="submit">Simpan pemetaan</button></form><?php else: ?>Impor RKAS aktif terlebih dahulu.<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></div><?php require __DIR__.'/../../includes/footer.php'; ?>
