<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (($_SESSION['role'] ?? '') === 'siswa') {
    http_response_code(403);
    exit('Fitur Magic Edit AI hanya tersedia untuk akun guru dan admin.');
}

$pageTitle = 'Magic Edit AI';
if (empty($_SESSION['magic_edit_csrf'])) {
    $_SESSION['magic_edit_csrf'] = bin2hex(random_bytes(32));
}
$magicEditCsrf = $_SESSION['magic_edit_csrf'];
$extraStylesheet = 'magic-edit.css?v=1';

require __DIR__ . '/../../includes/header.php';
?>
<section class="me-page">
  <div class="me-hero">
    <div class="me-hero-copy">
      <span class="me-eyebrow"><span class="me-sparkle">✦</span> AI CREATIVE STUDIO</span>
      <h1>Magic Edit <span>AI</span></h1>
      <p>Ubah foto cukup dengan sapuan brush dan instruksi. Tandai area yang ingin diedit, tulis perintah, lalu biarkan AI mengerjakannya.</p>
    </div>
    <div class="me-hero-chip"><span class="me-chip-dot"></span> Editor foto berbasis AI</div>
  </div>

  <div class="me-workspace">
    <section class="me-editor-card">
      <div class="me-card-head">
        <div>
          <h2>Kanvas kerja</h2>
          <p>Unggah foto, lalu tandai bagian yang ingin diubah.</p>
        </div>
        <label class="me-upload-btn" for="meFileInput">＋ Unggah foto</label>
        <input id="meFileInput" type="file" accept="image/jpeg,image/png,image/webp" hidden>
      </div>

      <div class="me-toolbar" aria-label="Alat seleksi">
        <div class="me-tool-group">
          <button class="me-tool is-active" id="meBrushBtn" type="button" data-tool="brush" title="Tandai area untuk diedit">🖌 Brush</button>
          <button class="me-tool" id="meEraserBtn" type="button" data-tool="eraser" title="Hapus tanda seleksi">◩ Penghapus</button>
        </div>
        <div class="me-tool-divider"></div>
        <div class="me-brush-control">
          <label for="meBrushSize">Ukuran brush <strong id="meBrushValue">36 px</strong></label>
          <input id="meBrushSize" type="range" min="8" max="100" value="36">
        </div>
        <div class="me-tool-actions">
          <button class="me-icon-btn" id="meUndoBtn" type="button" title="Urungkan" disabled>↶</button>
          <button class="me-icon-btn" id="meRedoBtn" type="button" title="Ulangi" disabled>↷</button>
          <button class="me-reset-btn" id="meResetBtn" type="button" disabled>Reset seleksi</button>
        </div>
      </div>

      <div class="me-stage" id="meStage">
        <div class="me-empty" id="meEmptyState">
          <div class="me-empty-icon">✧</div>
          <h3>Mulai dari sebuah foto</h3>
          <p>Seret foto ke area ini atau pilih dari perangkat Anda.</p>
          <label class="me-empty-upload" for="meFileInput">Pilih foto</label>
          <small>JPG, PNG, atau WebP · Maksimal 20 MB</small>
        </div>
        <canvas id="meCanvas" aria-label="Kanvas foto untuk seleksi brush" hidden></canvas>
      </div>

      <div class="me-stage-foot">
        <span><i class="me-legend-dot"></i> Area ungu adalah bagian yang dipilih untuk diedit.</span>
        <span id="meImageInfo">Belum ada foto</span>
      </div>
    </section>

    <aside class="me-controls-card">
      <div class="me-controls-head">
        <span class="me-step">01</span>
        <div><h2>Instruksi AI</h2><p>Ceritakan perubahan yang Anda inginkan.</p></div>
      </div>

      <label class="me-field-label" for="mePrompt">Apa yang ingin diubah?</label>
      <textarea id="mePrompt" rows="5" maxlength="2000" placeholder="Contoh: Ganti latar belakang menjadi studio foto profesional berwarna biru muda. Pertahankan wajah, ekspresi, dan bentuk tubuh orang pada foto."></textarea>
      <div class="me-prompt-hint"><span>✦</span> Semakin jelas instruksi, semakin terarah hasilnya.</div>

      <div class="me-suggestions">
        <span class="me-suggestions-title">Ide cepat</span>
        <button type="button" class="me-prompt-chip" data-prompt="Ganti latar belakang menjadi studio foto profesional dengan pencahayaan lembut. Pertahankan wajah dan identitas asli subjek.">Latar studio</button>
        <button type="button" class="me-prompt-chip" data-prompt="Ganti pakaian menjadi seragam Korpri yang rapi dan realistis. Pertahankan wajah, rambut, pose, dan identitas asli.">Seragam Korpri</button>
        <button type="button" class="me-prompt-chip" data-prompt="Hapus objek yang mengganggu pada area yang dipilih dan isi area tersebut secara alami sesuai latar sekitarnya.">Hapus objek</button>
        <button type="button" class="me-prompt-chip" data-prompt="Perbaiki pencahayaan agar terlihat seperti foto profesional, warna natural, detail tajam, dan tetap mempertahankan identitas asli.">Perbaiki cahaya</button>
      </div>

      <div class="me-setting-row">
        <label for="meQuality">Kualitas hasil</label>
        <select id="meQuality">
          <option value="high" selected>Tinggi</option>
          <option value="medium">Standar</option>
          <option value="low">Cepat</option>
        </select>
      </div>
      <div class="me-setting-row">
        <label for="meVariants">Jumlah variasi</label>
        <select id="meVariants">
          <option value="1" selected>1 hasil</option>
          <option value="2">2 hasil</option>
          <option value="3">3 hasil</option>
        </select>
      </div>

      <div class="me-mask-note">
        <span class="me-note-icon">ⓘ</span>
        <p><strong>Tips seleksi:</strong> Sapukan brush pada bagian foto yang ingin diganti. Tanpa seleksi, AI akan mengedit keseluruhan foto sesuai instruksi.</p>
      </div>

      <button class="me-generate-btn" id="meGenerateBtn" type="button" disabled>
        <span>✦</span> Buat dengan Magic Edit
      </button>
      <div class="me-privacy-note">Foto diproses untuk permintaan edit ini dan tidak disimpan sebagai data siswa/guru di database.</div>
    </aside>
  </div>

  <section class="me-results-card" id="meResultsCard" hidden>
    <div class="me-results-head">
      <div><span class="me-step">02</span><h2>Hasil Magic Edit</h2><p>Pilih hasil yang paling sesuai, lalu unduh.</p></div>
      <span class="me-result-status" id="meResultStatus">Selesai</span>
    </div>
    <div class="me-results-grid" id="meResultsGrid"></div>
  </section>

  <div class="me-loading" id="meLoading" hidden>
    <div class="me-loader"></div>
    <div><strong>AI sedang mengedit foto...</strong><span>Proses dapat memerlukan waktu. Jangan tutup halaman ini.</span></div>
  </div>
  <div class="me-alert" id="meAlert" role="alert" hidden></div>
</section>
<script>window.MAGIC_EDIT_CSRF = <?= json_encode($magicEditCsrf) ?>;</script>
<script src="<?= h($basePath) ?>/assets/js/magic-edit.js?v=1" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>