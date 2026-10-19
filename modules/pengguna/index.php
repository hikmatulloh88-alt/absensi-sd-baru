<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Hanya admin yang dapat mengelola akun.'); }
$pageTitle = 'Kelola Akun';
if (empty($_SESSION['csrf_akun'])) $_SESSION['csrf_akun'] = bin2hex(random_bytes(32));
$errors = []; $notice = '';
function akun_slug(string $nama): string {
  $nama = trim($nama);
  if (function_exists('iconv')) { $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nama); if ($ascii !== false) $nama = $ascii; }
  $slug = strtolower($nama);
  $slug = preg_replace('/[^a-z0-9]+/', '.', $slug) ?? '';
  return trim($slug, '.') ?: 'pengguna';
}
function akun_column_exists(PDO $pdo, string $table, string $column): bool {
  $q = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
  $q->execute([$table, $column]); return (bool)$q->fetchColumn();
}
function akun_table_exists(PDO $pdo, string $table): bool {
  $q = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'); $q->execute([$table]); return (bool)$q->fetchColumn();
}
$guruList = akun_table_exists($pdo, 'guru') ? $pdo->query("SELECT id,nama FROM guru WHERE status='aktif' ORDER BY nama")->fetchAll() : [];
$siswaList = akun_table_exists($pdo, 'siswa') ? $pdo->query("SELECT id,nama,nisn,nis,rombel FROM siswa WHERE status='aktif' ORDER BY nama")->fetchAll() : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_akun'], (string)($_POST['csrf'] ?? ''))) $errors[] = 'Sesi keamanan tidak valid. Muat ulang halaman.';
  else {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'generate_missing') {
      $created = 0; $skipped = 0; $reasons = [];
      $teacherIdColumn = akun_column_exists($pdo, 'guru', 'nip') ? 'nip' : (akun_column_exists($pdo, 'guru', 'nik') ? 'nik' : '');
      if ($teacherIdColumn !== '') {
        $teachers = $pdo->query("SELECT id,nama,`$teacherIdColumn` AS identitas FROM guru WHERE status='aktif' ORDER BY id")->fetchAll();
        foreach ($teachers as $person) {
          $identitas = trim((string)($person['identitas'] ?? ''));
          $check = $pdo->prepare('SELECT id FROM akun_pengguna WHERE guru_id=? LIMIT 1'); $check->execute([(int)$person['id']]);
          if ($check->fetch()) { $skipped++; continue; }
          if ($identitas === '') { $skipped++; $reasons[] = 'Guru tanpa NIP/NIK: ' . $person['nama']; continue; }
          $base = akun_slug((string)$person['nama']) . '@cbr.guru.id'; $username = $base; $suffix = 2;
          while (true) { $check = $pdo->prepare('SELECT id FROM akun_pengguna WHERE username=? LIMIT 1'); $check->execute([$username]); if (!$check->fetch()) break; $username = akun_slug((string)$person['nama']) . $suffix++ . '@cbr.guru.id'; }
          $ins = $pdo->prepare("INSERT INTO akun_pengguna (username,password_hash,nama,role,guru_id,siswa_id,aktif) VALUES (?,?,?,'guru',?,NULL,1)");
          $ins->execute([$username,password_hash($identitas,PASSWORD_DEFAULT),(string)$person['nama'],(int)$person['id']]); $created++;
        }
      } else { $reasons[] = 'Kolom NIP/NIK tidak ditemukan di tabel guru.'; }
      $students = $pdo->query("SELECT id,nama,nisn FROM siswa WHERE status='aktif' ORDER BY id")->fetchAll();
      foreach ($students as $person) {
        $identitas = trim((string)($person['nisn'] ?? ''));
        $check = $pdo->prepare('SELECT id FROM akun_pengguna WHERE siswa_id=? LIMIT 1'); $check->execute([(int)$person['id']]);
        if ($check->fetch()) { $skipped++; continue; }
        if ($identitas === '') { $skipped++; $reasons[] = 'Siswa tanpa NISN: ' . $person['nama']; continue; }
        $base = akun_slug((string)$person['nama']) . '@cbr.siswa.id'; $username = $base; $suffix = 2;
        while (true) { $check = $pdo->prepare('SELECT id FROM akun_pengguna WHERE username=? LIMIT 1'); $check->execute([$username]); if (!$check->fetch()) break; $username = akun_slug((string)$person['nama']) . $suffix++ . '@cbr.siswa.id'; }
        $ins = $pdo->prepare("INSERT INTO akun_pengguna (username,password_hash,nama,role,guru_id,siswa_id,aktif) VALUES (?,?,?,'siswa',NULL,?,1)");
        $ins->execute([$username,password_hash($identitas,PASSWORD_DEFAULT),(string)$person['nama'],(int)$person['id']]); $created++;
      }
      $notice = "Generate selesai: $created akun dibuat; $skipped data dilewati.";
      if ($reasons) $notice .= ' Perlu diperiksa: ' . implode('; ', array_slice($reasons,0,8)) . (count($reasons)>8 ? ' dan lainnya.' : '');
    } elseif ($action === 'create') {
      $username = trim((string)($_POST['username'] ?? ''));
      $nama = trim((string)($_POST['nama'] ?? ''));
      $role = (string)($_POST['role'] ?? '');
      $password = (string)($_POST['password'] ?? '');
      $guruId = null; $siswaId = null;
      if (!preg_match('/^[A-Za-z0-9._@-]{3,80}$/', $username)) $errors[] = 'Username 3–80 karakter; gunakan huruf, angka, titik, garis bawah, atau tanda hubung.';
      if ($nama === '' || mb_strlen($nama) > 150) $errors[] = 'Nama wajib diisi (maksimal 150 karakter).';
      if (!in_array($role, ['guru','siswa'], true)) $errors[] = 'Pilih jenis akun guru atau siswa.';
      if (strlen($password) < 10) $errors[] = 'Password minimal 10 karakter.';
      if ($role === 'guru') { $guruId = (int)($_POST['guru_id'] ?? 0); if (!$guruId || !array_filter($guruList, fn($g) => (int)$g['id'] === $guruId)) $errors[] = 'Pilih data guru aktif yang valid.'; }
      if ($role === 'siswa') { $siswaId = (int)($_POST['siswa_id'] ?? 0); if (!$siswaId || !array_filter($siswaList, fn($s) => (int)$s['id'] === $siswaId)) $errors[] = 'Pilih data siswa aktif yang valid.'; }
      if (!$errors) {
        $q = $pdo->prepare('SELECT id FROM akun_pengguna WHERE username = ?'); $q->execute([$username]);
        if ($q->fetch()) $errors[] = 'Username sudah digunakan.';
        $q = $pdo->prepare('SELECT id FROM akun_pengguna WHERE (guru_id = ? AND ? IS NOT NULL) OR (siswa_id = ? AND ? IS NOT NULL) LIMIT 1');
        $q->execute([$guruId,$guruId,$siswaId,$siswaId]);
        if ($q->fetch()) $errors[] = 'Data guru/siswa tersebut sudah memiliki akun.';
      }
      if (!$errors) {
        $q = $pdo->prepare('INSERT INTO akun_pengguna (username,password_hash,nama,role,guru_id,siswa_id,aktif) VALUES (?,?,?,?,?,?,1)');
        $q->execute([$username,password_hash($password, PASSWORD_DEFAULT),$nama,$role,$guruId,$siswaId]);
        $notice = 'Akun berhasil dibuat. Berikan username dan password kepada pemilik akun secara pribadi.';
      }
    } elseif ($action === 'toggle') {
      $id = (int)($_POST['id'] ?? 0);
      $q = $pdo->prepare("SELECT id,role,aktif FROM akun_pengguna WHERE id=?"); $q->execute([$id]); $target = $q->fetch();
      if (!$target) $errors[] = 'Akun tidak ditemukan.';
      elseif ($target['role'] === 'admin') $errors[] = 'Akun admin tidak dapat dinonaktifkan dari menu ini.';
      else { $q = $pdo->prepare('UPDATE akun_pengguna SET aktif = ? WHERE id = ?'); $q->execute([(int)!((int)$target['aktif']),$id]); $notice = 'Status akun diperbarui.'; }
    } elseif ($action === 'reset') {
      $id = (int)($_POST['id'] ?? 0); $password = (string)($_POST['password_baru'] ?? '');
      if (strlen($password) < 10) $errors[] = 'Password baru minimal 10 karakter.';
      $q = $pdo->prepare("SELECT id,role FROM akun_pengguna WHERE id=?"); $q->execute([$id]); $target = $q->fetch();
      if (!$target) $errors[] = 'Akun tidak ditemukan.';
      if (!$errors) { $q = $pdo->prepare('UPDATE akun_pengguna SET password_hash=? WHERE id=?'); $q->execute([password_hash($password,PASSWORD_DEFAULT),$id]); $notice = 'Password berhasil direset.'; }
    }
  }
}
$accounts = $pdo->query("SELECT a.id,a.username,a.nama,a.role,a.aktif,a.guru_id,a.siswa_id, g.nama AS nama_guru, s.nama AS nama_siswa, s.rombel FROM akun_pengguna a LEFT JOIN guru g ON g.id=a.guru_id LEFT JOIN siswa s ON s.id=a.siswa_id ORDER BY a.role,a.nama")->fetchAll();
require __DIR__ . '/../../includes/header.php';
?>
<style>
.account-grid{display:grid;grid-template-columns:minmax(280px,360px) minmax(0,1fr);gap:20px;align-items:start}.account-card{background:#fff;border:1px solid #e7ebf2;border-radius:16px;padding:20px;box-shadow:0 5px 18px #15223b08}.account-card h2{font-size:17px;margin:0 0 6px}.account-muted{color:#667085;font-size:12px;margin:0 0 16px}.account-field{display:grid;gap:6px;margin:13px 0}.account-field label{font-size:12px;font-weight:700;color:#344054}.account-field input,.account-field select{width:100%;padding:10px 11px;border:1px solid #d0d5dd;border-radius:9px;background:#fff;box-sizing:border-box}.account-btn{border:0;border-radius:9px;padding:9px 12px;background:#2459d3;color:#fff;font-weight:700;cursor:pointer}.account-btn.secondary{background:#eef2ff;color:#3448a5}.account-btn.danger{background:#fff0f0;color:#b42318}.account-table-wrap{overflow:auto}.account-table{border-collapse:collapse;width:100%;font-size:12px}.account-table th,.account-table td{padding:11px 9px;border-bottom:1px solid #edf0f5;text-align:left;vertical-align:top}.account-table th{color:#667085;background:#f8fafc}.account-actions{display:flex;gap:7px;flex-wrap:wrap}.account-actions form{display:flex;gap:5px}.account-actions input{width:125px;min-width:90px;padding:7px;border:1px solid #d0d5dd;border-radius:7px}.account-status{font-size:11px;font-weight:800;padding:4px 7px;border-radius:20px;background:#ecfdf3;color:#027a48}.account-status.off{background:#fff1f0;color:#b42318}.account-alert{padding:12px 14px;border-radius:10px;margin-bottom:14px;background:#ecfdf3;color:#027a48}.account-alert.error{background:#fff1f0;color:#b42318}.account-count{font-size:12px;color:#667085;margin-bottom:12px}@media(max-width:950px){.account-grid{grid-template-columns:1fr}}
</style>
<?php if ($notice): ?><div class="account-alert"><?= h($notice) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="account-alert error"><?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div><?php endif; ?>
<div class="account-grid">
<section class="account-card">
<h2>Generate Akun Otomatis</h2><p class="account-muted">Username dari nama: nama@cbr.guru.id atau nama@cbr.siswa.id. Password awal guru = NIP/NIK; siswa = NISN. Akun yang sudah ada tidak ditimpa.</p>
<form method="post" onsubmit="return confirm('Generate akun untuk semua guru dan siswa aktif yang belum memiliki akun? Catat dan bagikan kredensial secara aman.')"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_akun']) ?>"><input type="hidden" name="action" value="generate_missing"><button class="account-btn" type="submit">⚡ Generate Akun Guru &amp; Siswa</button></form>
<hr style="border:0;border-top:1px solid #edf0f5;margin:20px 0">
<h2>Buat Akun Manual</h2><p class="account-muted">Untuk satu akun khusus; akun ditautkan ke data yang sudah ada.</p>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_akun']) ?>"><input type="hidden" name="action" value="create">
<div class="account-field"><label>Jenis akun</label><select name="role" id="akunRole" required><option value="guru">Guru</option><option value="siswa">Siswa</option></select></div>
<div class="account-field"><label>Data guru</label><select name="guru_id" id="akunGuru"><option value="">-- Pilih guru aktif --</option><?php foreach($guruList as $g): ?><option value="<?= (int)$g['id'] ?>"><?= h($g['nama']) ?></option><?php endforeach; ?></select></div>
<div class="account-field" id="fieldSiswa" style="display:none"><label>Data siswa</label><select name="siswa_id" id="akunSiswa"><option value="">-- Pilih siswa aktif --</option><?php foreach($siswaList as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['nama']) ?><?= !empty($s['rombel']) ? ' · '.$s['rombel'] : '' ?><?= !empty($s['nisn']) ? ' · NISN '.$s['nisn'] : '' ?></option><?php endforeach; ?></select></div>
<div class="account-field"><label>Nama akun</label><input name="nama" maxlength="150" required placeholder="Nama guru / siswa"></div>
<div class="account-field"><label>Username</label><input name="username" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" placeholder="contoh: guru.siti"></div>
<div class="account-field"><label>Password awal</label><input type="password" name="password" required minlength="10" autocomplete="new-password" placeholder="Minimal 10 karakter"></div>
<button class="account-btn" type="submit">Buat Akun</button>
</form>
</section>
<section class="account-card"><h2>Daftar Akun</h2><p class="account-muted">Password tidak ditampilkan. Gunakan reset password jika pengguna lupa.</p>
<div class="account-count"><?= count($accounts) ?> akun terdaftar</div>
<div class="account-table-wrap"><table class="account-table"><thead><tr><th>Pengguna</th><th>Peran</th><th>Status</th><th>Pengaturan</th></tr></thead><tbody>
<?php foreach($accounts as $a): ?><tr><td><strong><?= h($a['nama']) ?></strong><br><span style="color:#667085">@<?= h($a['username']) ?></span><?php if($a['role']==='guru' && $a['nama_guru']): ?><br><small><?= h($a['nama_guru']) ?></small><?php elseif($a['role']==='siswa' && $a['nama_siswa']): ?><br><small><?= h($a['nama_siswa']) ?><?= $a['rombel'] ? ' · '.h($a['rombel']) : '' ?></small><?php endif; ?></td><td><?= h(strtoupper($a['role'])) ?></td><td><span class="account-status<?= $a['aktif'] ? '' : ' off' ?>"><?= $a['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td><td><div class="account-actions">
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_akun']) ?>"><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="password" name="password_baru" minlength="10" required placeholder="Password baru" aria-label="Password baru"><button class="account-btn secondary" type="submit">Reset</button></form>
<?php if($a['role']!=='admin'): ?><form method="post" onsubmit="return confirm('Ubah status akun ini?')"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_akun']) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="account-btn <?= $a['aktif'] ? 'danger' : 'secondary' ?>" type="submit"><?= $a['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form><?php endif; ?>
</div></td></tr><?php endforeach; ?>
<?php if(!$accounts): ?><tr><td colspan="4">Belum ada akun.</td></tr><?php endif; ?>
</tbody></table></div></section></div>
<script>
(function(){const role=document.getElementById('akunRole'),guru=document.getElementById('akunGuru'),siswa=document.getElementById('akunSiswa'),fieldSiswa=document.getElementById('fieldSiswa');function sync(){const isSiswa=role.value==='siswa';fieldSiswa.style.display=isSiswa?'grid':'none';guru.closest('.account-field').style.display=isSiswa?'none':'grid';guru.required=!isSiswa;siswa.required=isSiswa;}role.addEventListener('change',sync);sync();})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
