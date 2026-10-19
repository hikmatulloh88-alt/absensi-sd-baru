<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$pageTitle='Cetak Kartu QR';
if(!$pdo){http_response_code(500);require __DIR__.'/../../includes/header.php';echo '<div class="notice warning">Database belum tersambung.</div>';require __DIR__.'/../../includes/footer.php';exit;}
$jenis=(($_GET['jenis']??'siswa')==='guru')?'guru':'siswa';
$kelas=trim((string)($_GET['rombel']??''));
$kelasList=$pdo->query("SELECT DISTINCT rombel FROM siswa WHERE status='aktif' AND rombel IS NOT NULL AND TRIM(rombel)<>'' ORDER BY rombel")->fetchAll(PDO::FETCH_COLUMN);
if($jenis==='guru'){$q=$pdo->query("SELECT nama,nip AS kode,jabatan AS detail,'' AS rombel,NULL AS id FROM guru WHERE status='aktif' AND nip IS NOT NULL AND TRIM(nip)<>'' ORDER BY nama");}
else{$sql="SELECT id,nama,nisn,nis,COALESCE(NULLIF(TRIM(nisn),''),TRIM(nis)) AS kode,COALESCE(rombel,'') AS rombel FROM siswa WHERE status='aktif' AND ((nisn IS NOT NULL AND TRIM(nisn)<>'') OR (nis IS NOT NULL AND TRIM(nis)<>''))";$args=[];if($kelas!==''){$sql.=" AND rombel=?";$args[]=$kelas;}$sql.=" ORDER BY rombel,nama";$q=$pdo->prepare($sql);$q->execute($args);}
$people=$q->fetchAll();require __DIR__.'/../../includes/header.php';
?>
<section class="welcome no-print"><div><span class="eyebrow">KARTU IDENTITAS DIGITAL</span><h2>Cetak Kartu QR Code</h2><p>Cetak kartu absensi siswa dan guru dalam format A4.</p></div></section>
<section class="panel no-print" style="margin-bottom:16px"><form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap"><label>Jenis kartu<select name="jenis" onchange="this.form.submit()"><option value="siswa" <?=$jenis==='siswa'?'selected':''?>>Siswa</option><option value="guru" <?=$jenis==='guru'?'selected':''?>>Guru</option></select></label><?php if($jenis==='siswa'):?><label>Kelas<select name="rombel"><option value="">Semua Kelas</option><?php foreach($kelasList as $k):?><option value="<?=h($k)?>" <?=$kelas===$k?'selected':''?>><?=h($k)?></option><?php endforeach;?></select></label><?php endif;?><button type="submit">Tampilkan</button><button type="button" onclick="window.print()">Cetak Semua Kartu</button></form><p style="margin-top:10px"><?=count($people)?> kartu tersedia. Data tanpa NISN/NIS/NIP tidak ditampilkan.</p></section>
<?php if(!$people):?><div class="notice warning">Tidak ada data aktif dengan identitas kartu yang valid.</div><?php else:?><div class="qr-grid"><?php foreach($people as $p):
$photoUrl='';
if($jenis==='siswa' && !empty($p['id'])) {
  foreach(['jpg','jpeg','png','webp'] as $ext) {
    $candidate=__DIR__.'/../../assets/images/siswa/'.(int)$p['id'].'.'.$ext;
    if(is_file($candidate)) { $photoUrl='../../assets/images/siswa/'.(int)$p['id'].'.'.$ext; break; }
  }
}
?><article class="qr-card"><div class="card-bg"></div><div class="person-photo"><?php if($photoUrl!==''):?><img src="<?=h($photoUrl)?>" alt="Foto <?=h($p['nama'])?>"><?php else:?><span>FOTO</span><?php endif;?></div><h3><?=h($p['nama'])?></h3><p class="person-code"><?= $jenis==='guru'?'NIP':((trim((string)($p['nisn']??''))!=='')?'NISN':'NIS') ?>: <?=h($p['kode'])?></p><div class="qr-code" data-code="<?=h($p['kode'])?>"></div><footer>SCAN UNTUK ABSENSI</footer></article><?php endforeach;?></div><?php endif;?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>document.querySelectorAll('.qr-code').forEach(e=>{if(window.QRCode)new QRCode(e,{text:e.dataset.code,width:112,height:112,correctLevel:QRCode.CorrectLevel.M});else e.textContent='QR tidak termuat';});</script>
<style>
.qr-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;align-items:start;padding:1px;}
.qr-card{position:relative;isolation:isolate;overflow:hidden;min-height:0;height:390px;aspect-ratio:208/330;background:#fff url('../../assets/images/template_kartu_absensi.png') center center/100% 100% no-repeat;border:0;border-radius:12px;padding:82px 12px 12px;background-clip:padding-box;text-align:center;color:#14233a;break-inside:avoid;display:flex;flex-direction:column;align-items:center}
.person-photo{position:relative;z-index:1;width:104px;height:104px;border:4px solid #fff;outline:2px solid #1d5fb8;border-radius:50%;overflow:hidden;background:#e9f0f8;display:grid;place-items:center;box-shadow:0 4px 12px #163e6a20;margin:7px auto 10px;color:#6b7f99;font-size:11px;font-weight:800}
.person-photo img{display:block;width:100%;height:100%;object-fit:cover;aspect-ratio:1/1}.person-photo{flex:0 0 auto;aspect-ratio:1/1}
.qr-card h3{position:relative;z-index:1;font-size:13px;line-height:1.25;margin:0 0 5px;max-width:100%;overflow-wrap:anywhere;text-transform:uppercase;color:#132b50}
.person-code{position:relative;z-index:1;font-size:10px;font-weight:700;margin:0 0 2px;color:#294b78;overflow-wrap:anywhere}
.qr-code{position:relative;z-index:1;display:flex;justify-content:center;align-items:center;margin:4px auto 5px;background:#fff;border:4px solid white;box-shadow:0 0 0 1px #d7e1ee;border-radius:5px;padding:3px}
.qr-code canvas,.qr-code img{display:block;max-width:100%}
.qr-card footer{position:relative;z-index:2;width:100%;margin:0;padding:4px 1px 0;background:transparent;border-top:0;font-size:8px;line-height:1.25;font-weight:900;letter-spacing:1px;color:#12356b;text-shadow:none;box-sizing:border-box}
@media(max-width:850px){.qr-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:520px){.qr-grid{grid-template-columns:1fr}.qr-card{max-width:310px;width:100%;margin:auto}}
@media print{@page{size:A4 portrait;margin:7mm}.sidebar,.topbar,.no-print,.footer{display:none!important}.main-area{margin:0!important;width:100%!important}.page-content{padding:0!important}.qr-grid{grid-template-columns:repeat(3,54mm);grid-auto-rows:86mm;justify-content:center;column-gap:4mm;row-gap:3mm}.qr-card{box-sizing:border-box;width:54mm;min-width:54mm;max-width:54mm;min-height:0;height:86mm;aspect-ratio:54/86;border:0;border-radius:0;padding:14mm 2mm 1.5mm;-webkit-print-color-adjust:exact;print-color-adjust:exact}.person-photo{width:22mm;height:22mm;min-width:22mm;min-height:22mm;flex-basis:22mm}.qr-card h3{font-size:8pt;line-height:1.15;margin:0 0 1.2mm}.person-code{font-size:6.5pt;line-height:1.1;margin:0 0 1.5mm}.qr-code{margin:2mm auto 1.5mm;flex:0 0 auto}.qr-code canvas,.qr-code img{width:19mm!important;height:19mm!important}.qr-card footer{font-size:5.5pt;line-height:1.1;padding:1mm 0 0;margin:0;width:100%;background:transparent}}
</style>
<?php require __DIR__.'/../../includes/footer.php'; ?>