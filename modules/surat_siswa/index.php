<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$pageTitle = 'Pengajuan Izin / Sakit';
if (!$pdo) { http_response_code(500); exit('Database belum tersambung.'); }
if (empty($_SESSION['siswa_id'])) {
  require __DIR__ . '/../../includes/header.php';
  echo '<section class="panel"><h2>Pengajuan Izin / Sakit</h2><p>Halaman ini khusus akun siswa. Integrasi login siswa belum diaktifkan pada aplikasi ini.</p><p>Jangan memasukkan ID siswa lewat URL atau form. Login harus mengisi session siswa_id dari server setelah autentikasi.</p></section>';
  require __DIR__ . '/../../includes/footer.php'; exit;
}
$siswaId=(int)$_SESSION['siswa_id'];
$q=$pdo->prepare("SELECT id,nama,nisn,nis,rombel FROM siswa WHERE id=? AND status='aktif' LIMIT 1");$q->execute([$siswaId]);$siswa=$q->fetch();
if(!$siswa){http_response_code(403);exit('Akun siswa tidak valid atau tidak aktif.');}
$errors=[];$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals((string)($_SESSION['csrf_surat']??''),(string)($_POST['csrf']??''))) $errors[]='Sesi formulir kedaluwarsa. Muat ulang halaman.';
  $jenis=$_POST['jenis']??'';$mulai=$_POST['tanggal_mulai']??'';$selesai=$_POST['tanggal_selesai']??'';$keterangan=trim((string)($_POST['keterangan']??''));
  $validDate=static fn($v)=>is_string($v)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)&&strtotime($v)!==false&&date('Y-m-d',strtotime($v))===$v;
  if(!in_array($jenis,['izin','sakit'],true))$errors[]='Pilih jenis pengajuan yang benar.';
  if(!$validDate($mulai)||!$validDate($selesai)||$selesai<$mulai)$errors[]='Rentang tanggal tidak valid.';
  if($validDate($mulai)&&$validDate($selesai)&&(strtotime($selesai)-strtotime($mulai)>31*86400))$errors[]='Rentang pengajuan maksimal 32 hari.';
  if($keterangan===''||mb_strlen($keterangan)>500)$errors[]='Keterangan wajib diisi (maksimal 500 karakter).';
  if(!isset($_FILES['surat'])||$_FILES['surat']['error']!==UPLOAD_ERR_OK)$errors[]='Pilih foto surat atau PDF dengan ukuran maksimal 5 MB.';
  $tmp=$_FILES['surat']['tmp_name']??'';$original=basename((string)($_FILES['surat']['name']??'surat'));$size=(int)($_FILES['surat']['size']??0);
  if(!$errors){
    if($size<1||$size>5*1024*1024)$errors[]='Ukuran file maksimal 5 MB.';
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($tmp);
    $types=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($types[$mime]))$errors[]='Format yang diperbolehkan: PDF, JPG, PNG, atau WEBP.';
  }
  if(!$errors){
    $dir=__DIR__.'/../../storage/surat-absensi';
    if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))$errors[]='Folder penyimpanan surat gagal dibuat.';
    if(!$errors){
      $name=bin2hex(random_bytes(24)).'.'.$types[$mime];$target=$dir.'/'.$name;
      if(!move_uploaded_file($tmp,$target))$errors[]='File surat gagal disimpan.';
      else{
        try{$ins=$pdo->prepare("INSERT INTO pengajuan_surat_absensi(siswa_id,jenis,tanggal_mulai,tanggal_selesai,keterangan,file_path,file_asli) VALUES(?,?,?,?,?,?,?)");$ins->execute([$siswaId,$jenis,$mulai,$selesai,$keterangan,'storage/surat-absensi/'.$name,mb_substr($original,0,255)]);$success='Pengajuan berhasil dikirim dan menunggu verifikasi guru.';}
        catch(Throwable $e){@unlink($target);$errors[]='Pengajuan gagal disimpan. Pastikan migrasi database sudah dijalankan.';}
      }
    }
  }
}
if(empty($_SESSION['csrf_surat']))$_SESSION['csrf_surat']=bin2hex(random_bytes(32));
$hist=$pdo->prepare("SELECT jenis,tanggal_mulai,tanggal_selesai,keterangan,status,catatan_verifikasi,created_at FROM pengajuan_surat_absensi WHERE siswa_id=? ORDER BY created_at DESC LIMIT 30");$hist->execute([$siswaId]);$items=$hist->fetchAll();
require __DIR__.'/../../includes/header.php';
?>
<style>
.surat-hero{background:linear-gradient(120deg,#172d53,#245caa);color:white;border-radius:16px;padding:24px;margin-bottom:18px}.surat-hero h2{margin:0 0 7px;font:800 23px Manrope}.surat-hero p{margin:0;color:#d6e5ff}.surat-card{background:white;border:1px solid #e6edf5;border-radius:14px;padding:22px;margin-bottom:18px}.surat-form{display:grid;grid-template-columns:1fr 1fr;gap:15px}.surat-form label{display:grid;gap:7px;font-weight:700;font-size:12px;color:#41516b}.surat-form input,.surat-form select,.surat-form textarea{width:100%;padding:11px;border:1px solid #dce4ef;border-radius:9px;font:inherit}.surat-form .wide{grid-column:1/-1}.surat-submit{background:#245fda;color:white;border:0;border-radius:9px;padding:12px 17px;font-weight:800;cursor:pointer}.surat-table{width:100%;border-collapse:collapse;font-size:12px}.surat-table th,.surat-table td{text-align:left;padding:12px 9px;border-bottom:1px solid #edf1f6}.surat-table th{background:#f5f8fc}.surat-pill{border-radius:20px;padding:5px 9px;font-weight:800}.surat-menunggu{background:#fff4d7;color:#996400}.surat-disetujui{background:#e0f8eb;color:#167346}.surat-ditolak{background:#ffe6e8;color:#b42332}.surat-alert{padding:12px;border-radius:9px;margin-bottom:12px}.surat-error{background:#fff0f0;color:#a1222d}.surat-ok{background:#e8f9ef;color:#176d40}@media(max-width:700px){.surat-form{grid-template-columns:1fr}.surat-form .wide{grid-column:auto}.surat-card{padding:14px}.surat-table-wrap{overflow:auto}}
</style>
<section class="surat-hero"><h2>Pengajuan Izin & Sakit</h2><p>Halo, <?=h($siswa['nama'])?>. Unggah surat untuk ketidakhadiranmu dan pantau status verifikasinya.</p></section>
<?php if($success):?><div class="surat-alert surat-ok"><?=h($success)?></div><?php endif;?><?php if($errors):?><div class="surat-alert surat-error"><ul><?php foreach($errors as $e):?><li><?=h($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="surat-card"><h3>Kirim Pengajuan Baru</h3><form method="post" enctype="multipart/form-data" class="surat-form"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf_surat'])?>">
<label>Jenis Pengajuan<select name="jenis" required><option value="">Pilih jenis</option><option value="izin">Izin</option><option value="sakit">Sakit</option></select></label>
<label>Surat (PDF/JPG/PNG/WEBP, maks. 5 MB)<input type="file" name="surat" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required></label>
<label>Tanggal Mulai<input type="date" name="tanggal_mulai" required></label><label>Tanggal Selesai<input type="date" name="tanggal_selesai" required></label>
<label class="wide">Keterangan<textarea name="keterangan" rows="3" maxlength="500" required placeholder="Contoh: tidak masuk karena demam"></textarea></label>
<div class="wide"><button class="surat-submit" type="submit">Kirim untuk Verifikasi</button></div></form></section>
<section class="surat-card"><h3>Riwayat Pengajuan</h3><div class="surat-table-wrap"><table class="surat-table"><thead><tr><th>Tanggal Pengajuan</th><th>Jenis</th><th>Periode</th><th>Keterangan</th><th>Status</th><th>Catatan Guru</th></tr></thead><tbody><?php if(!$items):?><tr><td colspan="6">Belum ada pengajuan.</td></tr><?php else:foreach($items as $it):?><tr><td><?=h(date('d/m/Y',strtotime($it['created_at'])))?></td><td><?=h(ucfirst($it['jenis']))?></td><td><?=h($it['tanggal_mulai'])?> – <?=h($it['tanggal_selesai'])?></td><td><?=h($it['keterangan'])?></td><td><span class="surat-pill surat-<?=h($it['status'])?>"><?=h(ucfirst($it['status']))?></span></td><td><?=h($it['catatan_verifikasi']?:'-')?></td></tr><?php endforeach;endif;?></tbody></table></div></section>
<?php require __DIR__.'/../../includes/footer.php'; ?>
