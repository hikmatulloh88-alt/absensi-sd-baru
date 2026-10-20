<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Akses SPJ hanya untuk admin.'); }
$pageTitle='SPJ BOS Kinerja'; $errors=[]; $ok='';
try {
$pdo->exec("CREATE TABLE IF NOT EXISTS spj_transaksi (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
sumber_dana ENUM('BOSP','BOS_KINERJA') NOT NULL,
tanggal DATE NOT NULL, nomor_bukti VARCHAR(80) NOT NULL DEFAULT '',
kegiatan VARCHAR(180) NOT NULL, uraian VARCHAR(255) NOT NULL,
penyedia VARCHAR(180) NULL, nilai_bruto DECIMAL(15,2) NOT NULL DEFAULT 0,
pajak DECIMAL(15,2) NOT NULL DEFAULT 0, bukti_file VARCHAR(255) NULL,
created_by BIGINT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
INDEX idx_spj_sumber_tanggal(sumber_dana,tanggal), INDEX idx_spj_nomor_bukti(nomor_bukti)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch(Throwable $e){$errors[]='Tabel SPJ belum dapat disiapkan. Periksa izin database.';}
$uploadDir=__DIR__.'/bukti'; if(!is_dir($uploadDir)) @mkdir($uploadDir,0750,true);
if($_SERVER['REQUEST_METHOD']==='POST'&&!$errors){
if(!hash_equals((string)($_SESSION['spj_csrf']??''),(string)($_POST['csrf']??''))) $errors[]='Sesi formulir berakhir. Muat ulang halaman.';
else {
$tanggal=trim((string)($_POST['tanggal']??''));$nomor=trim((string)($_POST['nomor_bukti']??''));$kegiatan=trim((string)($_POST['kegiatan']??''));$uraian=trim((string)($_POST['uraian']??''));$penyedia=trim((string)($_POST['penyedia']??''));$bruto=(float)($_POST['nilai_bruto']??0);$pajak=(float)($_POST['pajak']??0);
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$tanggal)||$kegiatan===''||$uraian===''||$bruto<0||$pajak<0||$pajak>$bruto)$errors[]='Tanggal, kegiatan, uraian, dan nilai transaksi harus valid. Pajak tidak boleh melebihi nilai bruto.';
$filePath=null;
if(!$errors&&isset($_FILES['bukti'])&&$_FILES['bukti']['error']!==UPLOAD_ERR_NO_FILE){
$f=$_FILES['bukti'];$allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
if($f['error']!==UPLOAD_ERR_OK||$f['size']>8*1024*1024)$errors[]='Bukti maksimal 8 MB dan harus berhasil diunggah.';
else{$fi=new finfo(FILEINFO_MIME_TYPE);$mime=$fi->file($f['tmp_name'])?:'';
if(!isset($allowed[$mime]))$errors[]='Format bukti harus PDF, JPG, atau PNG.';
else{$name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];if(!is_dir($uploadDir)||!is_writable($uploadDir)||!move_uploaded_file($f['tmp_name'],$uploadDir.'/'.$name))$errors[]='Bukti gagal disimpan. Periksa izin folder modul SPJ.';else$filePath='bukti/'.$name;}
}
}
if(!$errors)try{$q=$pdo->prepare("INSERT INTO spj_transaksi(sumber_dana,tanggal,nomor_bukti,kegiatan,uraian,penyedia,nilai_bruto,pajak,bukti_file,created_by) VALUES('BOS_KINERJA',?,?,?,?,?,?,?,?,?)");$q->execute([$tanggal,$nomor,$kegiatan,$uraian,$penyedia?:null,$bruto,$pajak,$filePath,$_SESSION['akun_id']??null]);$ok='Transaksi SPJ BOS Kinerja tersimpan.';}catch(Throwable $e){$errors[]='Transaksi gagal disimpan. Periksa koneksi dan struktur database.';}
}}
if(empty($_SESSION['spj_csrf']))$_SESSION['spj_csrf']=bin2hex(random_bytes(32));
$filters=['awal'=>trim((string)($_GET['awal']??'')),'akhir'=>trim((string)($_GET['akhir']??'')),'cari'=>trim((string)($_GET['cari']??''))];$rows=[];$totBruto=0;$totPajak=0;
if(!$errors){$sql="SELECT * FROM spj_transaksi WHERE sumber_dana='BOS_KINERJA'";$args=[];
if($filters['awal']!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters['awal'])){$sql.=' AND tanggal>=?';$args[]=$filters['awal'];}
if($filters['akhir']!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters['akhir'])){$sql.=' AND tanggal<=?';$args[]=$filters['akhir'];}
if($filters['cari']!==''){$sql.=' AND (nomor_bukti LIKE ? OR kegiatan LIKE ? OR uraian LIKE ? OR penyedia LIKE ?)';$s='%'.$filters['cari'].'%';array_push($args,$s,$s,$s,$s);}
$sql.=' ORDER BY tanggal DESC,id DESC LIMIT 500';try{$q=$pdo->prepare($sql);$q->execute($args);$rows=$q->fetchAll();foreach($rows as $r){$totBruto+=(float)$r['nilai_bruto'];$totPajak+=(float)$r['pajak'];}}catch(Throwable $e){$errors[]='Data transaksi belum bisa dibaca.';}}
require __DIR__.'/../../includes/header.php';
?>
<style>
.spj-wrap{max-width:1280px;margin:auto}.spj-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0}.spj-metric,.spj-panel{background:#fff;border:1px solid #e6ebf2;border-radius:16px;padding:18px;box-shadow:0 8px 24px #1428500a}.spj-metric small,.spj-note{color:#718096}.spj-metric strong{display:block;font-size:24px;margin-top:8px}.spj-panel{margin:16px 0}.spj-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.spj-form label{display:grid;gap:6px;font-size:12px;font-weight:700;color:#475569}.spj-form input,.spj-form textarea,.spj-filters input{font:inherit;padding:10px 11px;border:1px solid #d7dfeb;border-radius:9px;width:100%;min-width:0}.spj-form textarea{min-height:42px}.spj-wide{grid-column:span 2}.spj-btn{border:0;border-radius:9px;background:#315ee8;color:white;padding:11px 15px;font-weight:800;cursor:pointer}.spj-table-wrap{overflow:auto}.spj-table{width:100%;border-collapse:collapse;min-width:800px}.spj-table th,.spj-table td{padding:10px;border-bottom:1px solid #edf0f5;text-align:left;font-size:12px}.spj-table th{background:#f7f9fc;color:#536176}.spj-alert,.spj-ok{padding:12px 14px;border-radius:10px;margin:12px 0}.spj-alert{background:#fff2f0;color:#a42b1f}.spj-ok{background:#ecfdf3;color:#087443}.spj-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}@media(max-width:800px){.spj-grid,.spj-form{grid-template-columns:1fr}.spj-wide{grid-column:auto}}@media print{.sidebar,.topbar,.spj-form-panel,.spj-filters,.spj-no-print,.footer{display:none!important}.main-area{margin:0!important;width:100%!important}.spj-panel,.spj-metric{box-shadow:none;border:1px solid #ccc;break-inside:avoid}.spj-table{min-width:0}.spj-table th,.spj-table td{font-size:9pt}}
</style>
<div class="spj-wrap">
<section class="welcome"><div><span class="eyebrow">KEUANGAN SEKOLAH</span><h2>SPJ BOS Kinerja</h2><p>Catatan transaksi dan arsip bukti dana BOS Kinerja.</p></div><div class="date-chip">Tahun <?=h(date('Y'))?></div></section>
<?php foreach($errors as $e):?><div class="spj-alert"><?=h($e)?></div><?php endforeach;?><?php if($ok):?><div class="spj-ok"><?=h($ok)?></div><?php endif;?>
<div class="spj-grid"><article class="spj-metric"><small>Total bruto pada daftar</small><strong>Rp <?=number_format($totBruto,0,',','.')?></strong></article><article class="spj-metric"><small>Total pajak dicatat</small><strong>Rp <?=number_format($totPajak,0,',','.')?></strong></article><article class="spj-metric"><small>Bruto dikurangi pajak</small><strong>Rp <?=number_format($totBruto-$totPajak,0,',','.')?></strong></article></div>
<section class="spj-panel spj-form-panel"><h3>Tambah transaksi BOS Kinerja</h3><p class="spj-note">Arsip pendamping; pencatatan resmi tetap mengikuti ARKAS.</p><form method="post" enctype="multipart/form-data" class="spj-form">
<input type="hidden" name="csrf" value="<?=h($_SESSION['spj_csrf'])?>">
<label>Tanggal<input required type="date" name="tanggal" value="<?=h(date('Y-m-d'))?>"></label><label>Nomor bukti<input name="nomor_bukti" maxlength="80"></label><label>Kegiatan<input required name="kegiatan" maxlength="180"></label>
<label class="spj-wide">Uraian belanja<textarea required name="uraian" maxlength="255"></textarea></label><label>Penyedia<input name="penyedia" maxlength="180"></label><label>Nilai bruto (Rp)<input required type="number" min="0" step=".01" name="nilai_bruto" value="0"></label><label>Pajak (Rp)<input required type="number" min="0" step=".01" name="pajak" value="0"></label><label>Bukti PDF/JPG/PNG maks 8 MB<input type="file" name="bukti" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"></label><div class="spj-wide"><button class="spj-btn">Simpan transaksi</button></div></form></section>
<section class="spj-panel"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap"><div><h3>Daftar transaksi BOS Kinerja</h3><p class="spj-note">Maksimal 500 baris.</p></div><button class="spj-btn spj-no-print" onclick="window.print()" type="button">Cetak rekap</button></div>
<form method="get" class="spj-filters spj-no-print"><input type="date" name="awal" aria-label="Tanggal awal" value="<?=h($filters['awal'])?>"><input type="date" name="akhir" aria-label="Tanggal akhir" value="<?=h($filters['akhir'])?>"><input name="cari" placeholder="Cari kegiatan/bukti/penyedia" value="<?=h($filters['cari'])?>"><button class="spj-btn">Filter</button><a href="index.php">Reset</a></form><div class="spj-table-wrap"><table class="spj-table"><thead><tr><th>Tanggal</th><th>No. bukti</th><th>Kegiatan</th><th>Uraian</th><th>Penyedia</th><th>Bruto</th><th>Pajak</th><th>Bukti</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="8">Belum ada transaksi yang sesuai.</td></tr><?php else:foreach($rows as $r):?><tr><td><?=h($r['tanggal'])?></td><td><?=h($r['nomor_bukti'])?></td><td><?=h($r['kegiatan'])?></td><td><?=h($r['uraian'])?></td><td><?=h($r['penyedia'])?></td><td>Rp <?=number_format((float)$r['nilai_bruto'],0,',','.')?></td><td>Rp <?=number_format((float)$r['pajak'],0,',','.')?></td><td><?php if($r['bukti_file']):?><a href="<?=h($r['bukti_file'])?>" target="_blank" rel="noopener">Lihat bukti</a><?php else:?>—<?php endif;?></td></tr><?php endforeach;endif;?>
</tbody></table></div></section><p class="spj-note">Modul internal pendamping, bukan pengganti ARKAS atau dokumen resmi pemerintah.</p></div>
<?php require __DIR__.'/../../includes/footer.php'; ?>
