<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (($_SESSION['role'] ?? '') === 'siswa') {
    http_response_code(403);
    exit('Magic Edit hanya tersedia untuk akun guru dan admin.');
}

$pageTitle = 'Magic Edit Lokal';
$extraStylesheet = 'magic-edit.css?v=2';
require __DIR__ . '/../../includes/header.php';
?>
<section class="me-page">
  <div class="me-hero">
    <div class="me-hero-copy">
      <span class="me-eyebrow"><span class="me-sparkle">✦</span> PHOTO EDITING STUDIO · LOCAL MODE</span>
      <h1>Magic Edit <span>Lokal</span></h1>
      <p>Edit foto langsung dari browser. Hapus objek kecil, hilangkan background sederhana, atur warna, lalu unduh hasilnya — tanpa API Key dan tanpa mengirim foto ke server.</p>
    </div>
    <div class="me-hero-chip"><span class="me-chip-dot"></span> Berjalan di perangkat ini</div>
  </div>

  <div class="me-workspace">
    <section class="me-editor-card">
      <div class="me-card-head">
        <div>
          <h2>Kanvas kerja</h2>
          <p>Unggah foto, pilih alat, lalu edit langsung di kanvas.</p>
        </div>
        <label class="me-upload-btn" for="meFileInput">＋ Unggah foto</label>
        <input id="meFileInput" type="file" accept="image/jpeg,image/png,image/webp" hidden>
      </div>

      <div class="me-toolbar" aria-label="Alat seleksi">
        <div class="me-tool-group">
          <button class="me-tool is-active" id="meBrushBtn" type="button" data-tool="brush" title="Tandai area yang ingin dihapus">🖌 Brush</button>
          <button class="me-tool" id="meEraserBtn" type="button" data-tool="eraser" title="Hapus tanda seleksi">◩ Penghapus</button>
          <button class="me-tool" id="mePickBgBtn" type="button" data-tool="pick" title="Klik warna background pada foto">◉ Pipet BG</button>
        </div>
        <div class="me-tool-divider"></div>
        <div class="me-brush-control">
          <label for="meBrushSize">Ukuran brush <strong id="meBrushValue">36 px</strong></label>
          <input id="meBrushSize" type="range" min="8" max="90" value="36">
        </div>
        <div class="me-tool-actions">
          <button class="me-icon-btn" id="meUndoBtn" type="button" title="Urungkan" disabled>↶</button>
          <button class="me-icon-btn" id="meRedoBtn" type="button" title="Ulangi" disabled>↷</button>
          <button class="me-reset-btn" id="meClearSelectionBtn" type="button" disabled>Hapus seleksi</button>
        </div>
      </div>

      <div class="me-stage" id="meStage">
        <div class="me-empty" id="meEmptyState">
          <div class="me-empty-icon">✧</div>
          <h3>Mulai dari sebuah foto</h3>
          <p>Seret foto ke area ini atau pilih dari perangkat Anda.</p>
          <label class="me-empty-upload" for="meFileInput">Pilih foto</label>
          <small>JPG, PNG, atau WebP · Maksimal 10 MB · Diperkecil otomatis agar ringan</small>
        </div>
        <canvas id="meCanvas" aria-label="Kanvas foto untuk seleksi dan pengeditan lokal" hidden></canvas>
      </div>

      <div class="me-stage-foot">
        <span><i class="me-legend-dot"></i> Ungu = area yang ditandai untuk dihapus.</span>
        <span id="meImageInfo">Belum ada foto</span>
      </div>
    </section>

    <aside class="me-controls-card">
      <div class="me-controls-head">
        <span class="me-step">01</span>
        <div><h2>Alat Magic Edit</h2><p>Semua proses berlangsung lokal di browser.</p></div>
      </div>

      <div class="me-local-badge"><span>✓</span> Tidak perlu API Key · Foto tidak diunggah</div>

      <div class="me-action-block">
        <span class="me-section-label">HAPUS OBJEK</span>
        <p>Tandai benda atau bagian foto dengan brush, lalu jalankan pengisian lokal dari piksel di sekitarnya.</p>
        <button class="me-primary-action" id="meEraseBtn" type="button" disabled>✦ Hapus area terpilih</button>
      </div>

      <div class="me-action-block">
        <span class="me-section-label">HAPUS BACKGROUND</span>
        <p>Pilih alat Pipet BG, klik warna background pada foto, atur toleransi, lalu hapus warnanya.</p>
        <div class="me-picked-row">
          <span class="me-color-swatch" id="meBgSwatch"></span>
          <span id="meBgColorLabel">Belum memilih warna</span>
        </div>
        <label class="me-range-label" for="meTolerance"><span>Toleransi warna</span><strong id="meToleranceValue">30</strong></label>
        <input class="me-wide-range" id="meTolerance" type="range" min="5" max="90" value="30">
        <button class="me-secondary-action" id="meBgBtn" type="button" disabled>Hapus background terpilih</button>
      </div>

      <div class="me-action-block">
        <span class="me-section-label">PENYESUAIAN FOTO</span>
        <label class="me-range-label" for="meBrightness"><span>Kecerahan</span><strong id="meBrightnessValue">0</strong></label>
        <input class="me-wide-range" id="meBrightness" type="range" min="-50" max="50" value="0">
        <label class="me-range-label" for="meContrast"><span>Kontras</span><strong id="meContrastValue">0</strong></label>
        <input class="me-wide-range" id="meContrast" type="range" min="-50" max="50" value="0">
        <label class="me-range-label" for="meSaturation"><span>Saturasi</span><strong id="meSaturationValue">100%</strong></label>
        <input class="me-wide-range" id="meSaturation" type="range" min="0" max="200" value="100">
        <button class="me-secondary-action" id="meApplyAdjustBtn" type="button" disabled>Terapkan penyesuaian</button>
        <button class="me-text-action" id="meResetAdjustBtn" type="button">Reset pengaturan warna</button>
      </div>

      <div class="me-mask-note">
        <span class="me-note-icon">ⓘ</span>
        <p><strong>Batas mode lokal:</strong> penghapusan objek memakai pengisian piksel sekitar dan paling cocok untuk objek kecil/latar sederhana. Mengganti pakaian atau menciptakan latar baru dari prompt memerlukan model AI generatif.</p>
      </div>

      <button class="me-generate-btn" id="meDownloadBtn" type="button" disabled><span>↓</span> Unduh hasil PNG</button>
      <button class="me-text-action me-restore-action" id="meRestoreBtn" type="button" disabled>Kembalikan foto asli</button>
      <div class="me-privacy-note">Foto diproses pada perangkat Anda. Modul ini tidak mengirim foto ke layanan eksternal dan tidak menyimpan foto ke database sekolah.</div>
    </aside>
  </div>

  <section class="me-results-card" id="meResultsCard" hidden>
    <div class="me-results-head">
      <div><span class="me-step">02</span><h2>Perbandingan hasil</h2><p>Foto asli dan hasil edit lokal.</p></div>
      <span class="me-result-status">Tersimpan di perangkat</span>
    </div>
    <div class="me-results-grid" id="meResultsGrid"></div>
  </section>

  <div class="me-loading" id="meLoading" hidden>
    <div class="me-loader"></div>
    <div><strong id="meLoadingTitle">Memproses foto...</strong><span>Proses berjalan di browser. Biarkan tab ini tetap terbuka.</span></div>
  </div>
  <div class="me-alert" id="meAlert" role="alert" hidden></div>
</section>
<script src="<?= h($basePath) ?>/assets/js/magic-edit.js?v=2" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>