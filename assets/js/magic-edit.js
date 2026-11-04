(() => {
  'use strict';

  const fileInput = document.getElementById('meFileInput');
  const stage = document.getElementById('meStage');
  const emptyState = document.getElementById('meEmptyState');
  const canvas = document.getElementById('meCanvas');
  const ctx = canvas.getContext('2d');
  const brushBtn = document.getElementById('meBrushBtn');
  const eraserBtn = document.getElementById('meEraserBtn');
  const brushSize = document.getElementById('meBrushSize');
  const brushValue = document.getElementById('meBrushValue');
  const undoBtn = document.getElementById('meUndoBtn');
  const redoBtn = document.getElementById('meRedoBtn');
  const resetBtn = document.getElementById('meResetBtn');
  const promptInput = document.getElementById('mePrompt');
  const generateBtn = document.getElementById('meGenerateBtn');
  const loading = document.getElementById('meLoading');
  const alertBox = document.getElementById('meAlert');
  const resultsCard = document.getElementById('meResultsCard');
  const resultsGrid = document.getElementById('meResultsGrid');
  const imageInfo = document.getElementById('meImageInfo');

  if (!fileInput || !canvas || !ctx) return;

  let baseImage = null;
  let normalizedFile = null;
  let originalUrl = null;
  let selectionCanvas = null;
  let selectionCtx = null;
  let overlayCanvas = null;
  let overlayCtx = null;
  let strokes = [];
  let history = [[]];
  let historyIndex = 0;
  let activeStroke = null;
  let activeTool = 'brush';

  const cloneStrokes = (items) => items.map(stroke => ({
    tool: stroke.tool,
    width: stroke.width,
    points: stroke.points.map(point => ({ x: point.x, y: point.y }))
  }));

  function showAlert(message) {
    alertBox.textContent = message;
    alertBox.hidden = false;
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearAlert() {
    alertBox.hidden = true;
    alertBox.textContent = '';
  }

  function updateHistoryButtons() {
    undoBtn.disabled = historyIndex <= 0;
    redoBtn.disabled = historyIndex >= history.length - 1;
    resetBtn.disabled = !baseImage || strokes.length === 0;
  }

  function drawStroke(target, stroke) {
    if (!stroke.points.length) return;
    target.save();
    target.globalCompositeOperation = stroke.tool === 'eraser' ? 'destination-out' : 'source-over';
    target.strokeStyle = '#ffffff';
    target.fillStyle = '#ffffff';
    target.lineWidth = stroke.width;
    target.lineCap = 'round';
    target.lineJoin = 'round';
    if (stroke.points.length === 1) {
      target.beginPath();
      target.arc(stroke.points[0].x, stroke.points[0].y, stroke.width / 2, 0, Math.PI * 2);
      target.fill();
    } else {
      target.beginPath();
      target.moveTo(stroke.points[0].x, stroke.points[0].y);
      for (let i = 1; i < stroke.points.length; i++) {
        target.lineTo(stroke.points[i].x, stroke.points[i].y);
      }
      target.stroke();
    }
    target.restore();
  }

  function redrawSelection() {
    if (!selectionCtx) return;
    selectionCtx.clearRect(0, 0, canvas.width, canvas.height);
    strokes.forEach(stroke => drawStroke(selectionCtx, stroke));
    if (activeStroke) drawStroke(selectionCtx, activeStroke);
    renderCanvas();
  }

  function renderCanvas() {
    if (!baseImage) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(baseImage, 0, 0, canvas.width, canvas.height);
    if (selectionCanvas && overlayCtx) {
      overlayCtx.clearRect(0, 0, canvas.width, canvas.height);
      overlayCtx.fillStyle = '#b35cff';
      overlayCtx.fillRect(0, 0, canvas.width, canvas.height);
      overlayCtx.globalCompositeOperation = 'destination-in';
      overlayCtx.drawImage(selectionCanvas, 0, 0);
      overlayCtx.globalCompositeOperation = 'source-over';
      ctx.save();
      ctx.globalAlpha = 0.48;
      ctx.drawImage(overlayCanvas, 0, 0);
      ctx.restore();
    }
  }

  function saveHistory() {
    history = history.slice(0, historyIndex + 1);
    history.push(cloneStrokes(strokes));
    historyIndex = history.length - 1;
    updateHistoryButtons();
  }

  function setTool(tool) {
    activeTool = tool;
    brushBtn.classList.toggle('is-active', tool === 'brush');
    eraserBtn.classList.toggle('is-active', tool === 'eraser');
    canvas.style.cursor = tool === 'brush' ? 'crosshair' : 'cell';
  }

  brushBtn.addEventListener('click', () => setTool('brush'));
  eraserBtn.addEventListener('click', () => setTool('eraser'));
  brushSize.addEventListener('input', () => {
    brushValue.textContent = brushSize.value + ' px';
  });

  async function prepareImage(file) {
    clearAlert();
    if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      showAlert('Format foto harus JPG, PNG, atau WebP.');
      return;
    }
    if (file.size > 20 * 1024 * 1024) {
      showAlert('Ukuran foto maksimal 20 MB.');
      return;
    }

    generateBtn.disabled = true;
    const tempUrl = URL.createObjectURL(file);
    try {
      const source = new Image();
      source.src = tempUrl;
      await source.decode();

      const maxEdge = 2048;
      const scale = Math.min(1, maxEdge / Math.max(source.naturalWidth, source.naturalHeight));
      const width = Math.max(64, Math.round(source.naturalWidth * scale));
      const height = Math.max(64, Math.round(source.naturalHeight * scale));
      const normalizedCanvas = document.createElement('canvas');
      normalizedCanvas.width = width;
      normalizedCanvas.height = height;
      const normalizedCtx = normalizedCanvas.getContext('2d');
      normalizedCtx.fillStyle = '#ffffff';
      normalizedCtx.fillRect(0, 0, width, height);
      normalizedCtx.drawImage(source, 0, 0, width, height);

      const blob = await new Promise(resolve => normalizedCanvas.toBlob(resolve, 'image/jpeg', 0.92));
      if (!blob) throw new Error('Foto tidak dapat diproses oleh browser.');
      normalizedFile = new File([blob], 'magic-edit-foto.jpg', { type: 'image/jpeg' });

      if (originalUrl) URL.revokeObjectURL(originalUrl);
      originalUrl = URL.createObjectURL(blob);
      baseImage = new Image();
      baseImage.src = originalUrl;
      await baseImage.decode();

      canvas.width = width;
      canvas.height = height;
      selectionCanvas = document.createElement('canvas');
      selectionCanvas.width = width;
      selectionCanvas.height = height;
      selectionCtx = selectionCanvas.getContext('2d');
      overlayCanvas = document.createElement('canvas');
      overlayCanvas.width = width;
      overlayCanvas.height = height;
      overlayCtx = overlayCanvas.getContext('2d');

      strokes = [];
      history = [[]];
      historyIndex = 0;
      activeStroke = null;
      canvas.hidden = false;
      emptyState.hidden = true;
      imageInfo.textContent = width + ' × ' + height + ' px';
      updateHistoryButtons();
      renderCanvas();
      generateBtn.disabled = false;
      resultsCard.hidden = true;
      resultsGrid.replaceChildren();
    } catch (error) {
      showAlert(error.message || 'Foto gagal dibuka. Coba gunakan file gambar lain.');
    } finally {
      URL.revokeObjectURL(tempUrl);
    }
  }

  fileInput.addEventListener('change', event => {
    const file = event.target.files && event.target.files[0];
    if (file) prepareImage(file);
  });

  stage.addEventListener('dragover', event => {
    event.preventDefault();
    stage.classList.add('is-dragging');
  });
  stage.addEventListener('dragleave', () => stage.classList.remove('is-dragging'));
  stage.addEventListener('drop', event => {
    event.preventDefault();
    stage.classList.remove('is-dragging');
    const file = event.dataTransfer.files && event.dataTransfer.files[0];
    if (file) prepareImage(file);
  });

  function pointerPosition(event) {
    const rect = canvas.getBoundingClientRect();
    return {
      x: Math.max(0, Math.min(canvas.width, (event.clientX - rect.left) * canvas.width / rect.width)),
      y: Math.max(0, Math.min(canvas.height, (event.clientY - rect.top) * canvas.height / rect.height))
    };
  }

  canvas.addEventListener('pointerdown', event => {
    if (!baseImage || generateBtn.disabled) return;
    event.preventDefault();
    canvas.setPointerCapture(event.pointerId);
    const point = pointerPosition(event);
    const rect = canvas.getBoundingClientRect();
    const ratio = canvas.width / Math.max(1, rect.width);
    activeStroke = {
      tool: activeTool,
      width: Math.max(2, Number(brushSize.value) * ratio),
      points: [point]
    };
    redrawSelection();
  });

  canvas.addEventListener('pointermove', event => {
    if (!activeStroke) return;
    const point = pointerPosition(event);
    activeStroke.points.push(point);
    redrawSelection();
  });

  function finishStroke() {
    if (!activeStroke) return;
    strokes.push(activeStroke);
    activeStroke = null;
    saveHistory();
    redrawSelection();
  }
  canvas.addEventListener('pointerup', finishStroke);
  canvas.addEventListener('pointercancel', finishStroke);

  undoBtn.addEventListener('click', () => {
    if (historyIndex <= 0) return;
    historyIndex--;
    strokes = cloneStrokes(history[historyIndex]);
    updateHistoryButtons();
    redrawSelection();
  });
  redoBtn.addEventListener('click', () => {
    if (historyIndex >= history.length - 1) return;
    historyIndex++;
    strokes = cloneStrokes(history[historyIndex]);
    updateHistoryButtons();
    redrawSelection();
  });
  resetBtn.addEventListener('click', () => {
    if (!baseImage) return;
    strokes = [];
    saveHistory();
    redrawSelection();
  });

  document.querySelectorAll('.me-prompt-chip').forEach(button => {
    button.addEventListener('click', () => {
      promptInput.value = button.dataset.prompt || '';
      promptInput.focus();
    });
  });

  function canvasBlob(target, type) {
    return new Promise((resolve, reject) => {
      target.toBlob(blob => blob ? resolve(blob) : reject(new Error('Mask seleksi tidak dapat dibuat.')), type);
    });
  }

  function renderResults(images) {
    resultsGrid.replaceChildren();
    images.forEach((item, index) => {
      const dataUrl = 'data:' + (item.mime || 'image/png') + ';base64,' + item.b64_json;
      const card = document.createElement('article');
      card.className = 'me-result-item';

      const top = document.createElement('div');
      top.className = 'me-result-top';
      const label = document.createElement('span');
      label.textContent = 'VARIASI ' + (index + 1);
      const download = document.createElement('a');
      download.className = 'me-download-btn';
      download.href = dataUrl;
      download.download = 'magic-edit-hasil-' + (index + 1) + '.png';
      download.textContent = '↓ Unduh PNG';
      top.append(label, download);

      const compare = document.createElement('div');
      compare.className = 'me-compare';
      const beforeWrap = document.createElement('div');
      const afterWrap = document.createElement('div');
      const beforeLabel = document.createElement('small');
      const afterLabel = document.createElement('small');
      beforeLabel.textContent = 'FOTO ASLI';
      afterLabel.textContent = 'HASIL AI';
      const before = document.createElement('img');
      const after = document.createElement('img');
      before.src = originalUrl;
      before.alt = 'Foto sebelum diedit';
      after.src = dataUrl;
      after.alt = 'Hasil Magic Edit variasi ' + (index + 1);
      beforeWrap.append(beforeLabel, before);
      afterWrap.append(afterLabel, after);
      compare.append(beforeWrap, afterWrap);
      card.append(top, compare);
      resultsGrid.append(card);
    });
    resultsCard.hidden = false;
    resultsCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  generateBtn.addEventListener('click', async () => {
    clearAlert();
    if (!normalizedFile) {
      showAlert('Unggah foto terlebih dahulu.');
      return;
    }
    const prompt = promptInput.value.trim();
    if (!prompt) {
      showAlert('Tulis instruksi perubahan foto terlebih dahulu.');
      promptInput.focus();
      return;
    }

    generateBtn.disabled = true;
    loading.hidden = false;
    resultsCard.hidden = true;
    try {
      const body = new FormData();
      body.append('csrf_token', window.MAGIC_EDIT_CSRF || '');
      body.append('image', normalizedFile, normalizedFile.name);
      body.append('prompt', prompt);
      body.append('quality', document.getElementById('meQuality').value);
      body.append('n', document.getElementById('meVariants').value);

      if (strokes.length > 0) {
        const maskCanvas = document.createElement('canvas');
        maskCanvas.width = canvas.width;
        maskCanvas.height = canvas.height;
        const maskCtx = maskCanvas.getContext('2d');
        maskCtx.fillStyle = '#000000';
        maskCtx.fillRect(0, 0, maskCanvas.width, maskCanvas.height);
        maskCtx.globalCompositeOperation = 'destination-out';
        maskCtx.drawImage(selectionCanvas, 0, 0);
        const maskBlob = await canvasBlob(maskCanvas, 'image/png');
        body.append('mask', maskBlob, 'magic-edit-mask.png');
      }

      const response = await fetch('edit.php', {
        method: 'POST',
        body,
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      const data = await response.json().catch(() => null);
      if (!response.ok || !data || !data.success) {
        throw new Error((data && data.message) || 'Proses edit gagal. Muat ulang halaman lalu coba lagi.');
      }
      renderResults(data.images || []);
    } catch (error) {
      showAlert(error.message || 'Terjadi kesalahan saat mengedit foto.');
    } finally {
      loading.hidden = true;
      generateBtn.disabled = !normalizedFile;
    }
  });

  setTool('brush');
  updateHistoryButtons();
})();