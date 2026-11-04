(() => {
  'use strict';

  const $ = id => document.getElementById(id);
  const fileInput = $('meFileInput');
  const stage = $('meStage');
  const emptyState = $('meEmptyState');
  const canvas = $('meCanvas');
  const displayCtx = canvas.getContext('2d', { willReadFrequently: true });
  const brushBtn = $('meBrushBtn');
  const eraserBtn = $('meEraserBtn');
  const pickBgBtn = $('mePickBgBtn');
  const brushSize = $('meBrushSize');
  const brushValue = $('meBrushValue');
  const undoBtn = $('meUndoBtn');
  const redoBtn = $('meRedoBtn');
  const clearSelectionBtn = $('meClearSelectionBtn');
  const eraseBtn = $('meEraseBtn');
  const bgBtn = $('meBgBtn');
  const downloadBtn = $('meDownloadBtn');
  const restoreBtn = $('meRestoreBtn');
  const tolerance = $('meTolerance');
  const toleranceValue = $('meToleranceValue');
  const bgSwatch = $('meBgSwatch');
  const bgColorLabel = $('meBgColorLabel');
  const brightness = $('meBrightness');
  const contrast = $('meContrast');
  const saturation = $('meSaturation');
  const applyAdjustBtn = $('meApplyAdjustBtn');
  const resetAdjustBtn = $('meResetAdjustBtn');
  const resultsCard = $('meResultsCard');
  const resultsGrid = $('meResultsGrid');
  const imageInfo = $('meImageInfo');
  const loading = $('meLoading');
  const loadingTitle = $('meLoadingTitle');
  const alertBox = $('meAlert');

  if (!fileInput || !canvas || !displayCtx) return;

  const MAX_EDGE = 1000;
  const MAX_FILE_BYTES = 10 * 1024 * 1024;
  const MAX_HISTORY = 7;

  let workCanvas = null;
  let workCtx = null;
  let selectionCanvas = null;
  let selectionCtx = null;
  let overlayCanvas = null;
  let overlayCtx = null;
  let originalUrl = null;
  let originalImageData = null;
  let currentResultUrl = null;
  let activeStroke = null;
  let strokes = [];
  let history = [];
  let historyIndex = -1;
  let activeTool = 'brush';
  let pickedBackground = null;
  let isBusy = false;

  function showAlert(message, type = 'error') {
    alertBox.textContent = message;
    alertBox.classList.toggle('me-alert-success', type === 'success');
    alertBox.hidden = false;
  }

  function clearAlert() {
    alertBox.hidden = true;
    alertBox.textContent = '';
    alertBox.classList.remove('me-alert-success');
  }

  function setBusy(busy, message) {
    isBusy = busy;
    loading.hidden = !busy;
    if (message) loadingTitle.textContent = message;
    [fileInput, brushBtn, eraserBtn, pickBgBtn, brushSize, undoBtn, redoBtn,
      clearSelectionBtn, eraseBtn, bgBtn, downloadBtn, restoreBtn,
      tolerance, brightness, contrast, saturation, applyAdjustBtn].forEach(control => {
      if (control) control.disabled = busy || (!workCanvas && control !== fileInput);
    });
    updateButtons();
  }

  function cloneStrokes(source) {
    return source.map(stroke => ({
      tool: stroke.tool,
      width: stroke.width,
      points: stroke.points.map(point => ({ x: point.x, y: point.y }))
    }));
  }

  function snapshot() {
    return {
      pixels: workCtx.getImageData(0, 0, workCanvas.width, workCanvas.height),
      strokes: cloneStrokes(strokes)
    };
  }

  function saveHistory() {
    history = history.slice(0, historyIndex + 1);
    history.push(snapshot());
    if (history.length > MAX_HISTORY) history.shift();
    historyIndex = history.length - 1;
    updateButtons();
  }

  function restoreHistory(index) {
    if (index < 0 || index >= history.length || isBusy) return;
    historyIndex = index;
    const state = history[historyIndex];
    workCtx.putImageData(state.pixels, 0, 0);
    strokes = cloneStrokes(state.strokes);
    redrawSelection();
    renderCanvas();
    updateButtons();
    updateComparison();
  }

  function updateButtons() {
    const hasImage = !!workCanvas;
    const hasSelection = hasImage && (strokes.length > 0 || !!activeStroke);
    undoBtn.disabled = isBusy || historyIndex <= 0;
    redoBtn.disabled = isBusy || historyIndex >= history.length - 1;
    clearSelectionBtn.disabled = isBusy || !hasSelection;
    eraseBtn.disabled = isBusy || !hasSelection;
    bgBtn.disabled = isBusy || !hasImage || !pickedBackground;
    downloadBtn.disabled = isBusy || !hasImage;
    restoreBtn.disabled = isBusy || !hasImage || history.length <= 1;
    applyAdjustBtn.disabled = isBusy || !hasImage;
  }

  function setTool(tool) {
    activeTool = tool;
    brushBtn.classList.toggle('is-active', tool === 'brush');
    eraserBtn.classList.toggle('is-active', tool === 'eraser');
    pickBgBtn.classList.toggle('is-active', tool === 'pick');
    canvas.style.cursor = tool === 'pick' ? 'copy' : (tool === 'eraser' ? 'cell' : 'crosshair');
  }

  function drawStroke(target, stroke) {
    if (!stroke || !stroke.points.length) return;
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
    selectionCtx.clearRect(0, 0, selectionCanvas.width, selectionCanvas.height);
    strokes.forEach(stroke => drawStroke(selectionCtx, stroke));
    if (activeStroke) drawStroke(selectionCtx, activeStroke);
    renderCanvas();
  }

  function renderCanvas() {
    if (!workCanvas) return;
    displayCtx.clearRect(0, 0, canvas.width, canvas.height);
    displayCtx.drawImage(workCanvas, 0, 0);
    if (selectionCanvas && overlayCtx) {
      overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
      overlayCtx.globalCompositeOperation = 'source-over';
      overlayCtx.fillStyle = '#a65cff';
      overlayCtx.fillRect(0, 0, overlayCanvas.width, overlayCanvas.height);
      overlayCtx.globalCompositeOperation = 'destination-in';
      overlayCtx.drawImage(selectionCanvas, 0, 0);
      overlayCtx.globalCompositeOperation = 'source-over';
      displayCtx.save();
      displayCtx.globalAlpha = 0.48;
      displayCtx.drawImage(overlayCanvas, 0, 0);
      displayCtx.restore();
    }
    updateButtons();
  }

  function selectionHasPixels() {
    if (!selectionCtx || !selectionCanvas) return false;
    const pixels = selectionCtx.getImageData(0, 0, selectionCanvas.width, selectionCanvas.height).data;
    for (let i = 3; i < pixels.length; i += 4) {
      if (pixels[i] > 24) return true;
    }
    return false;
  }

  function clearSelection(save = true) {
    if (!selectionCtx) return;
    strokes = [];
    activeStroke = null;
    selectionCtx.clearRect(0, 0, selectionCanvas.width, selectionCanvas.height);
    if (save) saveHistory();
    renderCanvas();
  }

  async function prepareImage(file) {
    clearAlert();
    if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      showAlert('Format foto harus JPG, PNG, atau WebP.');
      return;
    }
    if (file.size > MAX_FILE_BYTES) {
      showAlert('Ukuran foto maksimal 10 MB agar tetap ringan pada laptop dengan RAM 4 GB.');
      return;
    }

    setBusy(true, 'Menyiapkan foto lokal...');
    const temporaryUrl = URL.createObjectURL(file);
    try {
      const source = new Image();
      source.src = temporaryUrl;
      await source.decode();
      if (source.naturalWidth * source.naturalHeight > 30000000) {
        throw new Error('Resolusi foto terlalu besar. Gunakan foto maksimal sekitar 30 megapiksel.');
      }

      const scale = Math.min(1, MAX_EDGE / Math.max(source.naturalWidth, source.naturalHeight));
      const width = Math.max(1, Math.round(source.naturalWidth * scale));
      const height = Math.max(1, Math.round(source.naturalHeight * scale));

      workCanvas = document.createElement('canvas');
      workCanvas.width = width;
      workCanvas.height = height;
      workCtx = workCanvas.getContext('2d', { willReadFrequently: true });
      workCtx.clearRect(0, 0, width, height);
      workCtx.drawImage(source, 0, 0, width, height);

      selectionCanvas = document.createElement('canvas');
      selectionCanvas.width = width;
      selectionCanvas.height = height;
      selectionCtx = selectionCanvas.getContext('2d', { willReadFrequently: true });
      overlayCanvas = document.createElement('canvas');
      overlayCanvas.width = width;
      overlayCanvas.height = height;
      overlayCtx = overlayCanvas.getContext('2d');

      canvas.width = width;
      canvas.height = height;
      strokes = [];
      history = [];
      historyIndex = -1;
      activeStroke = null;
      pickedBackground = null;
      bgSwatch.style.background = 'transparent';
      bgColorLabel.textContent = 'Belum memilih warna';
      bgBtn.disabled = true;

      if (originalUrl) URL.revokeObjectURL(originalUrl);
      const originalBlob = await new Promise(resolve => workCanvas.toBlob(resolve, 'image/png'));
      if (!originalBlob) throw new Error('Foto tidak dapat disiapkan untuk pratinjau.');
      originalUrl = URL.createObjectURL(originalBlob);
      originalImageData = workCtx.getImageData(0, 0, width, height);

      emptyState.hidden = true;
      canvas.hidden = false;
      imageInfo.textContent = width + ' × ' + height + ' px · Mode ringan';
      saveHistory();
      renderCanvas();
      resultsCard.hidden = true;
      resultsGrid.replaceChildren();
    } catch (error) {
      showAlert(error.message || 'Foto gagal dibuka. Coba file lain.');
      workCanvas = null;
      workCtx = null;
      canvas.hidden = true;
      emptyState.hidden = false;
    } finally {
      URL.revokeObjectURL(temporaryUrl);
      setBusy(false);
    }
  }

  function pointerPosition(event) {
    const rect = canvas.getBoundingClientRect();
    return {
      x: Math.max(0, Math.min(canvas.width - 1, (event.clientX - rect.left) * canvas.width / rect.width)),
      y: Math.max(0, Math.min(canvas.height - 1, (event.clientY - rect.top) * canvas.height / rect.height))
    };
  }

  function eraseSelectedArea() {
    if (!workCanvas || !selectionCtx || isBusy) return;
    clearAlert();
    const width = workCanvas.width;
    const height = workCanvas.height;
    const total = width * height;
    const source = workCtx.getImageData(0, 0, width, height);
    const maskPixels = selectionCtx.getImageData(0, 0, width, height).data;
    const mask = new Uint8Array(total);
    let selectedCount = 0;

    for (let i = 0, p = 0; i < total; i++, p += 4) {
      if (maskPixels[p + 3] > 24) {
        mask[i] = 1;
        selectedCount++;
      }
    }
    if (!selectedCount) {
      showAlert('Tandai bagian foto yang ingin dihapus dengan brush terlebih dahulu.');
      return;
    }
    if (selectedCount > total * 0.55) {
      showAlert('Area seleksi terlalu luas untuk laptop ini. Pilih bagian yang lebih kecil agar proses tetap ringan.');
      return;
    }

    setBusy(true, 'Mengisi area terpilih dari piksel di sekitarnya...');
    window.setTimeout(() => {
      try {
        const rgba = new Uint8ClampedArray(source.data);
        const known = new Uint8Array(total);
        const queued = new Uint8Array(total);
        const queue = new Uint32Array(selectedCount);
        for (let i = 0; i < total; i++) if (!mask[i]) known[i] = 1;

        let tail = 0;
        const neighbors = [-width - 1, -width, -width + 1, -1, 1, width - 1, width, width + 1];
        for (let y = 0; y < height; y++) {
          for (let x = 0; x < width; x++) {
            const index = y * width + x;
            if (!mask[index]) continue;
            let boundary = false;
            for (const offset of neighbors) {
              const n = index + offset;
              const nx = n % width;
              const ny = Math.floor(n / width);
              if (n >= 0 && n < total && Math.abs(nx - x) <= 1 && Math.abs(ny - y) <= 1 && known[n]) {
                boundary = true;
                break;
              }
            }
            if (boundary) {
              queue[tail++] = index;
              queued[index] = 1;
            }
          }
        }

        let head = 0;
        let filled = 0;
        while (head < tail) {
          const waveEnd = tail;
          const waveLength = waveEnd - head;
          const updateIndices = new Uint32Array(waveLength);
          const updateColors = new Uint8ClampedArray(waveLength * 4);
          let updates = 0;

          for (let q = head; q < waveEnd; q++) {
            const index = queue[q];
            const x = index % width;
            const y = Math.floor(index / width);
            let red = 0, green = 0, blue = 0, weightSum = 0;
            for (const offset of neighbors) {
              const n = index + offset;
              if (n < 0 || n >= total) continue;
              const nx = n % width;
              const ny = Math.floor(n / width);
              if (Math.abs(nx - x) > 1 || Math.abs(ny - y) > 1 || !known[n]) continue;
              const diagonal = Math.abs(offset) === width + 1 || Math.abs(offset) === width - 1;
              const weight = diagonal ? 0.7071 : 1;
              const p = n * 4;
              red += rgba[p] * weight;
              green += rgba[p + 1] * weight;
              blue += rgba[p + 2] * weight;
              weightSum += weight;
            }
            if (weightSum > 0) {
              const p = index * 4;
              updateIndices[updates] = index;
              updateColors[updates * 4] = red / weightSum;
              updateColors[updates * 4 + 1] = green / weightSum;
              updateColors[updates * 4 + 2] = blue / weightSum;
              updateColors[updates * 4 + 3] = source.data[p + 3] || 255;
              updates++;
            }
          }

          head = waveEnd;
          for (let i = 0; i < updates; i++) {
            const index = updateIndices[i];
            const p = index * 4;
            rgba[p] = updateColors[i * 4];
            rgba[p + 1] = updateColors[i * 4 + 1];
            rgba[p + 2] = updateColors[i * 4 + 2];
            rgba[p + 3] = updateColors[i * 4 + 3];
            known[index] = 1;
            filled++;
          }
          for (let i = 0; i < updates; i++) {
            const index = updateIndices[i];
            const x = index % width;
            const y = Math.floor(index / width);
            for (const offset of neighbors) {
              const n = index + offset;
              if (n < 0 || n >= total || !mask[n] || queued[n]) continue;
              const nx = n % width;
              const ny = Math.floor(n / width);
              if (Math.abs(nx - x) > 1 || Math.abs(ny - y) > 1) continue;
              queued[n] = 1;
              queue[tail++] = n;
            }
          }
          if (!updates) break;
        }

        if (!filled) {
          showAlert('Area seleksi tidak berbatasan dengan piksel foto yang tersisa. Kurangi area seleksi lalu coba lagi.');
          return;
        }

        const output = new ImageData(rgba, width, height);
        workCtx.putImageData(output, 0, 0);
        strokes = [];
        selectionCtx.clearRect(0, 0, width, height);
        saveHistory();
        renderCanvas();
        updateComparison();
        showAlert('Area terpilih sudah diisi secara lokal. Hasil terbaik biasanya untuk noda atau objek kecil dengan latar yang relatif rata; area besar dapat terlihat buram.', 'success');
      } catch (error) {
        showAlert('Penghapusan objek gagal: ' + (error.message || 'memori browser tidak cukup.'));
      } finally {
        setBusy(false);
      }
    }, 30);
  }

  eraseBtn.addEventListener('click', eraseSelectedArea);

  function colorDistance(r, g, b, target) {
    const dr = r - target.r;
    const dg = g - target.g;
    const db = b - target.b;
    return Math.sqrt(dr * dr + dg * dg + db * db);
  }

  function removePickedBackground() {
    if (!workCanvas || !pickedBackground || isBusy) return;
    clearAlert();
    const width = workCanvas.width;
    const height = workCanvas.height;
    const total = width * height;
    const source = workCtx.getImageData(0, 0, width, height);
    const pixels = source.data;
    const limit = Math.max(8, Number(tolerance.value) * 2);
    const limitSquared = limit * limit;
    const visited = new Uint8Array(total);
    const removed = new Uint8Array(total);
    const queue = new Uint32Array(total);
    let head = 0, tail = 0;

    setBusy(true, 'Menghapus warna background yang tersambung...');
    window.setTimeout(() => {
      try {
        // The eyedropper stores the exact pixel selected by the user.
        const startIndex = pickedBackground.index;
        if (!Number.isInteger(startIndex) || startIndex < 0 || startIndex >= total) {
          showAlert('Pilih warna background sekali lagi menggunakan alat Pipet BG.');
          return;
        }
        const startPixel = startIndex * 4;
        const target = { r: pixels[startPixel], g: pixels[startPixel + 1], b: pixels[startPixel + 2] };
        const neighbors = [-width, -1, 1, width];
        queue[tail++] = startIndex;
        visited[startIndex] = 1;
        while (head < tail) {
          const index = queue[head++];
          const p = index * 4;
          if (pixels[p + 3] === 0 || colorDistance(pixels[p], pixels[p + 1], pixels[p + 2], target) > limit) continue;
          removed[index] = 1;
          for (const offset of neighbors) {
            const next = index + offset;
            if (next < 0 || next >= total || visited[next]) continue;
            const x = index % width;
            if ((offset === -1 && x === 0) || (offset === 1 && x === width - 1)) continue;
            visited[next] = 1;
            queue[tail++] = next;
          }
        }

        let removedCount = 0;
        for (let i = 0; i < total; i++) {
          if (removed[i]) {
            pixels[i * 4 + 3] = 0;
            removedCount++;
          }
        }
        if (!removedCount) {
          showAlert('Warna yang dipilih tidak dapat dihapus. Coba klik area background lain atau naikkan toleransi warna.');
          return;
        }

        // Add a small anti-aliased fringe around the removed region.
        const feather = 38;
        for (let index = 0; index < total; index++) {
          if (!removed[index]) continue;
          const x = index % width;
          const y = Math.floor(index / width);
          const edgeNeighbors = [];
          if (x > 0) edgeNeighbors.push(index - 1);
          if (x < width - 1) edgeNeighbors.push(index + 1);
          if (y > 0) edgeNeighbors.push(index - width);
          if (y < height - 1) edgeNeighbors.push(index + width);
          for (const next of edgeNeighbors) {
            if (removed[next]) continue;
            const p = next * 4;
            const distance = colorDistance(pixels[p], pixels[p + 1], pixels[p + 2], target);
            if (distance > limit && distance < limit + feather) {
              const alpha = Math.round(255 * ((distance - limit) / feather));
              pixels[p + 3] = Math.min(pixels[p + 3], alpha);
            }
          }
        }

        workCtx.putImageData(source, 0, 0);
        saveHistory();
        renderCanvas();
        updateComparison();
        showAlert('Background yang terhubung dengan warna pilihan berhasil dibuat transparan. Periksa tepi foto; hasil bergantung pada keseragaman warna background.', 'success');
      } catch (error) {
        showAlert('Penghapusan background gagal: ' + (error.message || 'memori browser tidak cukup.'));
      } finally {
        setBusy(false);
      }
    }, 30);
  }

  bgBtn.addEventListener('click', removePickedBackground);

  function updateAdjustmentLabels() {
    $('meBrightnessValue').textContent = brightness.value;
    $('meContrastValue').textContent = contrast.value;
    $('meSaturationValue').textContent = saturation.value + '%';
  }
  [brightness, contrast, saturation].forEach(input => input.addEventListener('input', updateAdjustmentLabels));

  applyAdjustBtn.addEventListener('click', () => {
    if (!workCanvas || isBusy) return;
    clearAlert();
    setBusy(true, 'Menerapkan penyesuaian warna...');
    window.setTimeout(() => {
      try {
        const image = workCtx.getImageData(0, 0, workCanvas.width, workCanvas.height);
        const data = image.data;
        const bright = Number(brightness.value) * 2.55;
        const c = Number(contrast.value) * 2.0;
        const factor = (259 * (c + 255)) / (255 * (259 - c));
        const sat = Number(saturation.value) / 100;
        for (let i = 0; i < data.length; i += 4) {
          if (data[i + 3] === 0) continue;
          let r = factor * (data[i] - 128) + 128 + bright;
          let g = factor * (data[i + 1] - 128) + 128 + bright;
          let b = factor * (data[i + 2] - 128) + 128 + bright;
          const gray = 0.2126 * r + 0.7152 * g + 0.0722 * b;
          r = gray + (r - gray) * sat;
          g = gray + (g - gray) * sat;
          b = gray + (b - gray) * sat;
          data[i] = Math.max(0, Math.min(255, r));
          data[i + 1] = Math.max(0, Math.min(255, g));
          data[i + 2] = Math.max(0, Math.min(255, b));
        }
        workCtx.putImageData(image, 0, 0);
        brightness.value = 0;
        contrast.value = 0;
        saturation.value = 100;
        updateAdjustmentLabels();
        saveHistory();
        renderCanvas();
        updateComparison();
        showAlert('Penyesuaian foto berhasil diterapkan secara lokal.', 'success');
      } catch (error) {
        showAlert('Penyesuaian foto gagal: ' + (error.message || 'memori browser tidak cukup.'));
      } finally {
        setBusy(false);
      }
    }, 20);
  });

  resetAdjustBtn.addEventListener('click', () => {
    brightness.value = 0;
    contrast.value = 0;
    saturation.value = 100;
    updateAdjustmentLabels();
  });

  function updateComparison() {
    if (!workCanvas || !originalUrl) return Promise.resolve();
    return new Promise(resolve => {
      workCanvas.toBlob(blob => {
        if (!blob) return resolve();
        if (currentResultUrl) URL.revokeObjectURL(currentResultUrl);
        currentResultUrl = URL.createObjectURL(blob);
        resultsGrid.replaceChildren();
        const card = document.createElement('article');
        card.className = 'me-result-item';
        const compare = document.createElement('div');
        compare.className = 'me-compare';
        const beforeWrap = document.createElement('div');
        const afterWrap = document.createElement('div');
        const beforeLabel = document.createElement('small');
        const afterLabel = document.createElement('small');
        const before = document.createElement('img');
        const after = document.createElement('img');
        beforeLabel.textContent = 'FOTO ASLI';
        afterLabel.textContent = 'HASIL SEKARANG';
        before.src = originalUrl;
        before.alt = 'Foto asli sebelum edit';
        after.src = currentResultUrl;
        after.alt = 'Foto setelah edit lokal';
        beforeWrap.append(beforeLabel, before);
        afterWrap.append(afterLabel, after);
        compare.append(beforeWrap, afterWrap);
        card.append(compare);
        resultsGrid.append(card);
        resultsCard.hidden = false;
        resolve();
      }, 'image/png');
    });
  }

  downloadBtn.addEventListener('click', () => {
    if (!workCanvas || isBusy) return;
    workCanvas.toBlob(blob => {
      if (!blob) {
        showAlert('File hasil tidak dapat dibuat. Coba lagi.');
        return;
      }
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'magic-edit-lokal.png';
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1500);
    }, 'image/png');
  });

  restoreBtn.addEventListener('click', () => {
    if (!workCanvas || !originalImageData || isBusy) return;
    workCtx.putImageData(originalImageData, 0, 0);
    strokes = [];
    selectionCtx.clearRect(0, 0, selectionCanvas.width, selectionCanvas.height);
    pickedBackground = null;
    brightness.value = 0;
    contrast.value = 0;
    saturation.value = 100;
    updateAdjustmentLabels();
    bgSwatch.style.background = 'transparent';
    bgColorLabel.textContent = 'Belum memilih warna';
    saveHistory();
    renderCanvas();
    updateComparison();
    showAlert('Foto dikembalikan ke kondisi asli.', 'success');
  });

  function sampleBackground(point) {
    const x = Math.round(point.x);
    const y = Math.round(point.y);
    const pixel = workCtx.getImageData(x, y, 1, 1).data;
    pickedBackground = { r: pixel[0], g: pixel[1], b: pixel[2], index: y * workCanvas.width + x };
    const hex = '#' + [pixel[0], pixel[1], pixel[2]].map(v => v.toString(16).padStart(2, '0')).join('');
    bgSwatch.style.background = hex;
    bgColorLabel.textContent = hex.toUpperCase();
    bgBtn.disabled = false;
    clearAlert();
  }

  function finishStroke() {
    if (!activeStroke) return;
    strokes.push(activeStroke);
    activeStroke = null;
    saveHistory();
    redrawSelection();
  }

  function onPointerDown(event) {
    if (!workCanvas || isBusy) return;
    event.preventDefault();
    const point = pointerPosition(event);
    if (activeTool === 'pick') {
      sampleBackground(point);
      return;
    }
    canvas.setPointerCapture(event.pointerId);
    const rect = canvas.getBoundingClientRect();
    const scale = canvas.width / Math.max(1, rect.width);
    activeStroke = {
      tool: activeTool,
      width: Math.max(2, Number(brushSize.value) * scale),
      points: [point]
    };
    redrawSelection();
  }

  // Rebind pointer handlers after image state is initialized; handlers remain safe before upload.
  canvas.addEventListener('pointerdown', onPointerDown);
  canvas.addEventListener('pointermove', event => {
    if (!activeStroke || isBusy) return;
    activeStroke.points.push(pointerPosition(event));
    redrawSelection();
  });
  canvas.addEventListener('pointerup', finishStroke);
  canvas.addEventListener('pointercancel', finishStroke);

  setTool('brush');
  updateAdjustmentLabels();
  updateButtons();
})();