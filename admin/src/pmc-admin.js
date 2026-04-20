/**
 * Pro Media Cropper - Main Admin Module
 */
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import * as pdfjsLib from 'pdfjs-dist';
import './pmc-admin.css';
import pdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;

function pmcInit(rootEl = document) {
    const q = (id) => rootEl.querySelector(`#${id}`);

    const customOpt = q('pmc-ratio-preset')?.querySelector('option[value="custom"]');
    if (customOpt) {
        customOpt.dataset.w   = pmc_vars.export_width;
        customOpt.dataset.h   = pmc_vars.export_height;
        customOpt.textContent = `Custom (${pmc_vars.export_width}×${pmc_vars.export_height})`;
    }

    // Elements
    const canvas       = q('pmc-canvas');
    const ctx          = canvas.getContext('2d');
    const img          = q('pmc-image');
    const loader       = q('pmc-loading');
    const filenameInput = q('pmc-filename');
    const statusCont   = q('pmc-status-container');
    const attrLine     = q('pmc-attribution');
    const presetSel    = q('pmc-ratio-preset');

    // State
    let cropper = null;
    let isLocked = true;
    let currentMeta = {};
    let stockPage = 1;
    let stockLoading = false;
    let currentBlobUrl = null;
    let exportW, exportH;

    // Initialisation
    presetSel.value = pmc_vars.default_ratio;
    

    function updateCanvasSize() {
        const opt = presetSel.selectedOptions[0];
        exportW = parseInt(opt.dataset.w);
        exportH = parseInt(opt.dataset.h);
        canvas.width  = exportW;
        canvas.height = exportH;
        q('pmc-preview-label').textContent = `Export Preview (${exportW}x${exportH})`;
        if (cropper) initCropper();
    }

    async function renderPdf(file) {
        const arrayBuffer = await file.arrayBuffer();
        const pdf  = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        const page = await pdf.getPage(1);
        const vp   = page.getViewport({ scale: 3.0 });
        const c    = document.createElement('canvas');
        c.height   = vp.height;
        c.width    = vp.width;
        await page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
        return c.toDataURL('image/png');
    }

    function clearUI() {
        if (cropper) { cropper.destroy(); cropper = null; }
        if (currentBlobUrl?.startsWith('blob:')) URL.revokeObjectURL(currentBlobUrl);
        currentBlobUrl = null;
        img.src = '';
        img.classList.remove('loaded');
        currentMeta = {};
        attrLine.innerHTML = '';
        ctx.clearRect(0, 0, exportW, exportH);
        q('pmc-save-btn').disabled = true;
        statusCont.innerHTML = '';
    }

    function loadSource(url, name, meta = {}) {
        if (!url) return;
        if (cropper) { cropper.destroy(); cropper = null; }
        if (currentBlobUrl?.startsWith('blob:')) URL.revokeObjectURL(currentBlobUrl);

        img.src = '';
        img.classList.remove('loaded');
        loader.style.display = 'flex';

        filenameInput.value = (name || 'image-' + Date.now())
            .toLowerCase().replace(/\.[^/.]+$/, '').replace(/\s+/g, '-');

        currentMeta  = meta;
        attrLine.innerHTML = meta.display_path || '';

        const isBlob     = meta.isBlob || url.startsWith('blob:') || url.startsWith('data:');
        const isExternal = url.startsWith('http') && !url.includes(window.location.hostname);

        let finalUrl;
        if (isBlob) {
            img.removeAttribute('crossOrigin');
            finalUrl = url;
        } else if (isExternal) {
            img.removeAttribute('crossOrigin');
            finalUrl = `${pmc_vars.ajaxurl}?action=pmc_proxy_image&url=${encodeURIComponent(url)}`;
        } else {
            img.crossOrigin = 'anonymous';
            finalUrl = url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now();
        }

        img.src = finalUrl;
        img.onload = () => {
            initCropper();
            img.classList.add('loaded');
            q('pmc-save-btn').disabled = false;
            loader.style.display = 'none';
        };
        img.onerror = () => {
            alert('Failed to load image. The source may be blocking external requests.');
            loader.style.display = 'none';
        };
    }

    function initCropper() {
        if (cropper) cropper.destroy();
        cropper = new Cropper(img, {
            aspectRatio: isLocked ? exportW / exportH : NaN,
            viewMode: 1,
            cropmove: update,
            crop: update,
            ready: update,
        });
    }

    function update() {
        if (!cropper || !cropper.ready) return;
        const data = cropper.getData();
        if (Math.floor(data.width) <= 0 || Math.floor(data.height) <= 0) return;

        const crop = cropper.getCroppedCanvas({
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        if (!crop) return;

        if (isLocked) {
            let finalW, finalH;
            if (pmc_vars.save_exact) {
                finalW = exportW; finalH = exportH;
            } else {
                let ratio = Math.min(exportW / crop.width, exportH / crop.height);
                if (ratio > 1) ratio = 1;
                finalW = Math.round(crop.width  * ratio);
                finalH = Math.round(crop.height * ratio);
            }
            if (canvas.width !== finalW || canvas.height !== finalH) {
                canvas.width  = finalW;
                canvas.height = finalH;
                q('pmc-preview-label').textContent = `Export Preview (${finalW}x${finalH})`;
            }
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(crop, 0, 0, finalW, finalH);

        } else {
            let canvasW, canvasH;
            if (pmc_vars.save_exact) {
                canvasW = exportW; canvasH = exportH;
            } else {
                const scaleToFitCrop = Math.max(crop.width / exportW, crop.height / exportH);
                const canvasScale    = Math.min(scaleToFitCrop, 1);
                canvasW = Math.round(exportW * canvasScale);
                canvasH = Math.round(exportH * canvasScale);
            }
            if (canvas.width !== canvasW || canvas.height !== canvasH) {
                canvas.width  = canvasW;
                canvas.height = canvasH;
                q('pmc-preview-label').textContent = `Export Preview (${canvasW}x${canvasH})`;
            }
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            const mode = q('pmc-mode').value;
            if (mode === 'echo') {
                ctx.save();
                ctx.filter = `blur(${q('pmc-blur').value}px) brightness(0.6)`;
                ctx.drawImage(crop, -20, -20, canvasW + 40, canvasH + 40);
                ctx.restore();
            } else {
                ctx.fillStyle = mode === 'white'  ? '#FFF'
                              : mode === 'custom' ? q('pmc-color').value
                              : '#000';
                ctx.fillRect(0, 0, canvasW, canvasH);
            }

            const imgScale = Math.min(canvasW / crop.width, canvasH / crop.height,
                                      pmc_vars.save_exact ? Infinity : 1);
            const drawW = Math.round(crop.width  * imgScale);
            const drawH = Math.round(crop.height * imgScale);
            ctx.drawImage(crop,
                Math.round((canvasW - drawW) / 2),
                Math.round((canvasH - drawH) / 2),
                drawW, drawH);
        }
    }

    // ── Event listeners ───────────────────────────────────────────────────────

    q('pmc-file-input').onchange = async (e) => {
        const f = e.target.files[0]; if (!f) return;
        loader.style.display = 'flex';
        try {
            const url = f.type === 'application/pdf' ? await renderPdf(f) : URL.createObjectURL(f);
            loadSource(url, f.name, { display_path: 'Local File: ' + f.name });
        } catch { alert('Error loading file.'); loader.style.display = 'none'; }
        e.target.value = '';
    };

    q('pmc-paste-btn').onclick = async () => {
        try {
            const clipboardItems = await navigator.clipboard.read();
            for (const item of clipboardItems) {
                const imageType = item.types.find(t => t.startsWith('image/'));
                if (imageType) {
                    loadSource(URL.createObjectURL(await item.getType(imageType)),
                               'pasted-image-' + Date.now(), { isBlob: true });
                    return;
                }
                if (item.types.includes('text/plain')) {
                    const text = await (await item.getType('text/plain')).text();
                    if (text.trim().startsWith('http')) {
                        loadSource(text.trim(), 'pasted-url-' + Date.now(), { display_path: 'Pasted URL' });
                        return;
                    }
                }
            }
            alert('No image or URL found in clipboard.');
        } catch {
            const fallback = prompt('Paste Image URL here:');
            if (fallback?.trim().startsWith('http'))
                loadSource(fallback.trim(), 'pasted-url-' + Date.now(), { display_path: 'Pasted URL' });
        }
    };

    q('pmc-library-btn').onclick = (e) => {
        e.preventDefault();
        const frame = wp.media({ title: 'Select Image', multiple: false, library: { type: 'image' } });
        frame.on('select', () => {
            const a = frame.state().get('selection').first().toJSON();
            loadSource(a.url, a.filename, { display_path: 'Media Library: ' + a.title });
        });
        frame.open();
    };

    presetSel.onchange = updateCanvasSize;

    q('mode-locked').onclick = function () {
        isLocked = true;
        this.classList.add('active');
        q('mode-pillar').classList.remove('active');
        q('pillarbox-controls').style.display = 'none';
        initCropper();
    };

    q('mode-pillar').onclick = function () {
        isLocked = false;
        this.classList.add('active');
        q('mode-locked').classList.remove('active');
        q('pillarbox-controls').style.display = 'block';
        initCropper();
    };

    q('pmc-mode').onchange = function () {
        q('blur-wrap').style.display         = this.value === 'echo'   ? 'block' : 'none';
        q('color-picker-wrap').style.display = this.value === 'custom' ? 'block' : 'none';
        update();
    };

    q('pmc-blur').oninput  = update;
    q('pmc-color').oninput = update;

    q('pmc-stock-btn').onclick = () => {
        q('pmc-search-modal').style.display = 'block';
        q('pmc-stock-query').focus();
    };

    q('pmc-eyedropper-btn').onclick = function () {
        const btn = this;
        if (btn.classList.contains('pmc-eyedropper-active')) { cancel(); return; }
        btn.classList.add('pmc-eyedropper-active');
        btn.textContent = 'Cancel';
        canvas.classList.add('selecting');
        canvas.onclick = (e) => {
            const rect = canvas.getBoundingClientRect();
            const x = Math.floor((e.clientX - rect.left) * (canvas.width  / rect.width));
            const y = Math.floor((e.clientY - rect.top)  * (canvas.height / rect.height));
            try {
                const pixel = ctx.getImageData(x, y, 1, 1).data;
                q('pmc-color').value = '#' + Array.from(pixel.slice(0, 3))
                    .map(v => v.toString(16).padStart(2, '0')).join('');
                update();
            } catch { console.error('Canvas tainted.'); }
            cancel();
        };
        function cancel() {
            btn.classList.remove('pmc-eyedropper-active');
            btn.textContent = 'Pick';
            canvas.classList.remove('selecting');
            canvas.onclick = null;
        }
    };

    q('pmc-save-btn').onclick = function () {
        const btn  = this;
        const icon = btn.querySelector('.dashicons');
        btn.disabled = true;
        icon.classList.replace('dashicons-cloud-upload', 'dashicons-update');

        canvas.toBlob((blob) => {
            const fd = new FormData();
            fd.append('file', blob, (filenameInput.value || 'crop') + '.jpg');
            fd.append('description', currentMeta.description || '');
            fetch(pmc_vars.root + 'wp/v2/media', {
                method: 'POST',
                headers: { 'X-WP-Nonce': pmc_vars.nonce },
                body: fd,
            })
            .then(r => r.json())
            .then(res => {
                icon.classList.replace('dashicons-update', 'dashicons-cloud-upload');
                btn.disabled = false;
                if (res.id) {
                    statusCont.innerHTML = `<div style="color:green;font-weight:600;">✅ Saved! <a href="${res.link}" target="_blank">View</a></div>`;
                    setTimeout(() => statusCont.innerHTML = '', 8000);
                } else {
                    statusCont.innerHTML = '<div style="color:red;">Save failed.</div>';
                }
            });
        }, 'image/jpeg', 0.92);
    };

    // Stock search — scoped to this rootEl so nested modal works correctly
    window.pmcStartNewSearch = function () {
        stockPage = 1; stockLoading = false;
        q('pmc-stock-results').innerHTML = '<div id="pmc-stock-load-sentinel"></div>';
        const sentinel = q('pmc-stock-load-sentinel');
        const obs = new IntersectionObserver(
            (es) => { if (es[0].isIntersecting && !stockLoading) fetchStock(); },
            { root: q('pmc-stock-results'), threshold: 0.1 }
        );
        obs.observe(sentinel);
        fetchStock();
    };

    function fetchStock() {
        const query = q('pmc-stock-query').value;
        if (!query || stockLoading) return;
        stockLoading = true;
        const sentinel = q('pmc-stock-load-sentinel');
        sentinel.innerHTML = '<div class="pmc-spinner"></div>';
        jQuery.post(pmc_vars.ajaxurl, {
            action:    'pmc_search_stock',
            query,
            provider:  q('pmc-stock-provider').value,
            page:      stockPage,
        }, (res) => {
            if (res.success && res.data.length) {
                res.data.forEach(item => {
                    const i = document.createElement('img');
                    i.src     = item.thumb;
                    i.onclick = () => {
                        q('pmc-search-modal').style.display = 'none';
                        loadSource(item.full, query, item);
                    };
                    q('pmc-stock-results').insertBefore(i, sentinel);
                });
                stockPage++; stockLoading = false; sentinel.innerHTML = '';
            } else {
                sentinel.textContent = 'No more results.'; stockLoading = false;
            }
        });
    }

    q('pmc-stock-provider').onchange = () => {
        if (q('pmc-stock-query').value.trim()) window.pmcStartNewSearch();
    };
    q('pmc-stock-query').onkeypress = (e) => { if (e.key === 'Enter') window.pmcStartNewSearch(); };

    // ── Expose for modal tab ──────────────────────────────────────────────────

    window.pmcDestroy = function () {
        if (cropper) { cropper.destroy(); cropper = null; }
    };

    window.pmcExportAndSave = function () {
        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                const fd = new FormData();
                fd.append('file', blob, (filenameInput.value || 'crop') + '.jpg');
                fd.append('description', currentMeta.description || '');
                fetch(pmc_vars.root + 'wp/v2/media', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': pmc_vars.nonce },
                    body: fd,
                })
                .then(r => r.json())
                .then(resolve)
                .catch(reject);
            }, 'image/jpeg', 0.92);
        });
    };

    // ── Boot ──────────────────────────────────────────────────────────────────
    updateCanvasSize();
}

// Standalone admin page — init immediately against document
if (document.getElementById('pmc-canvas')) {
    pmcInit(document);
}

// Expose for modal tab
window.pmcInit = pmcInit;