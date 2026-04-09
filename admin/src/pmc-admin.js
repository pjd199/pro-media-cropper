/**
 * Pro Media Cropper - Main Admin Module
 */
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import * as pdfjsLib from 'pdfjs-dist';

import './pmc-admin.css'; // Your custom styles

// Vite-specific way to get the worker URL from node_modules
import pdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;

// Elements
const canvas = document.getElementById('pmc-canvas');
const ctx = canvas.getContext('2d');
const img = document.getElementById('pmc-image');
const loader = document.getElementById('pmc-loading');
const filenameInput = document.getElementById('pmc-filename');
const statusCont = document.getElementById('pmc-status-container');
const attrLine = document.getElementById('pmc-attribution');
const presetSel = document.getElementById('pmc-ratio-preset');

// State
let cropper = null;
let isLocked = true;
let currentMeta = {};
let stockPage = 1;
let stockLoading = false;
let currentBlobUrl = null;
let exportW, exportH;

// Initialization
presetSel.value = pmc_vars.default_ratio;

function updateCanvasSize() {
    const opt = presetSel.selectedOptions[0];
    exportW = parseInt(opt.dataset.w);
    exportH = parseInt(opt.dataset.h);
    canvas.width = exportW;
    canvas.height = exportH;
    document.getElementById('pmc-preview-label').textContent = `Export Preview (${exportW}x${exportH})`;
    if (cropper) initCropper();
}

async function renderPdf(file) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = pmc_vars.pdf_worker_url;

    const arrayBuffer = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    const page = await pdf.getPage(1);
    const vp = page.getViewport({ scale: 3.0 });

    const c = document.createElement('canvas');
    c.height = vp.height;
    c.width = vp.width;

    await page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
    return c.toDataURL('image/png');
}

function clearUI() {
    if (cropper) { cropper.destroy(); cropper = null; }
    if (currentBlobUrl && currentBlobUrl.startsWith('blob:')) URL.revokeObjectURL(currentBlobUrl);
    currentBlobUrl = null;
    img.src = '';
    img.classList.remove('loaded');
    currentMeta = {};
    attrLine.innerHTML = '';
    ctx.clearRect(0, 0, exportW, exportH);
    document.getElementById('pmc-save-btn').disabled = true;
    statusCont.innerHTML = '';
}

function loadSource(url, name, meta = {}) {
    if (!url) return;

    if (cropper) { cropper.destroy(); cropper = null; }
    if (currentBlobUrl && currentBlobUrl.startsWith('blob:')) URL.revokeObjectURL(currentBlobUrl);

    img.src = '';
    img.classList.remove('loaded');
    loader.style.display = 'flex';

    const safeName = (name || "image-" + Date.now());
    filenameInput.value = safeName.toLowerCase().replace(/\.[^/.]+$/, "").replace(/\s+/g, '-');

    currentMeta = meta;
    attrLine.innerHTML = meta.display_path || '';
    let finalUrl = url;

    const isBlob = meta.isBlob || url.startsWith('blob:') || url.startsWith('data:');
    const isExternal = url.startsWith('http') && !url.includes(window.location.hostname);

    if (isBlob) {
        img.removeAttribute('crossOrigin');
        finalUrl = url;
    } else if (isExternal) {
        const proxyBase = pmc_vars.ajaxurl;
        finalUrl = `${proxyBase}?action=pmc_proxy_image&url=${encodeURIComponent(url)}`;
        img.removeAttribute('crossOrigin');
    } else {
        img.crossOrigin = "anonymous";
        finalUrl = url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now();
    }

    img.src = finalUrl;
    img.onload = () => {
        initCropper();
        img.classList.add('loaded');
        document.getElementById('pmc-save-btn').disabled = false;
        loader.style.display = 'none';
    };

    img.onerror = () => {
        alert("Failed to load image. The source may be blocking external requests.");
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
        ready: update
    });
}

function update() {
    if (!cropper || !cropper.ready) return;
    const data = cropper.getData();
    if (Math.floor(data.width) <= 0 || Math.floor(data.height) <= 0) {
        ctx.clearRect(0, 0, exportW, exportH);
        return;
    }

    const crop = cropper.getCroppedCanvas({
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
    });

    if (!crop) return;
    ctx.clearRect(0, 0, exportW, exportH);

    if (!isLocked) {
        const mode = document.getElementById('pmc-mode').value;
        if (mode === 'echo') {
            ctx.save();
            ctx.filter = `blur(${document.getElementById('pmc-blur').value}px) brightness(0.6)`;
            ctx.drawImage(crop, -20, -20, exportW + 40, exportH + 40);
            ctx.restore();
        } else {
            ctx.fillStyle = (mode === 'white') ? "#FFF" : (mode === 'custom' ? document.getElementById('pmc-color').value : "#000");
            ctx.fillRect(0, 0, exportW, exportH);
        }
    } else {
        ctx.fillStyle = "#000";
        ctx.fillRect(0, 0, exportW, exportH);
    }

    const r = Math.min(exportW / crop.width, exportH / crop.height);
    const nw = crop.width * r;
    const nh = crop.height * r;
    ctx.drawImage(crop, (exportW - nw) / 2, (exportH - nh) / 2, nw, nh);
}

// Event Listeners
document.getElementById('pmc-file-input').onchange = async (e) => {
    const f = e.target.files[0]; if (!f) return;
    loader.style.display = 'flex';
    try {
        const url = (f.type === 'application/pdf') ? await renderPdf(f) : URL.createObjectURL(f);
        loadSource(url, f.name, { display_path: 'Local File: ' + f.name });
    } catch (err) { alert('Error loading file.'); loader.style.display = 'none'; }
    e.target.value = '';
};

document.getElementById('pmc-paste-btn').onclick = async () => {
    try {
        const clipboardItems = await navigator.clipboard.read();
        for (const item of clipboardItems) {
            const imageType = item.types.find(type => type.startsWith('image/'));
            if (imageType) {
                const blob = await item.getType(imageType);
                const url = URL.createObjectURL(blob);
                loadSource(url, "pasted-image-" + Date.now(), { isBlob: true });
                return;
            }
            if (item.types.includes('text/plain')) {
                const textBlob = await item.getType('text/plain');
                const text = await textBlob.text();
                const trimmed = text.trim();
                if (trimmed.startsWith('http')) {
                    loadSource(trimmed, "pasted-url-" + Date.now(), { display_path: 'Pasted URL' });
                    return;
                }
            }
        }
        alert("No image or URL found in clipboard.");
    } catch (err) {
        const fallback = prompt("Paste Image URL here:");
        if (fallback && fallback.trim().startsWith('http')) {
            loadSource(fallback.trim(), "pasted-url-" + Date.now(), { display_path: 'Pasted URL' });
        }
    }
};

document.getElementById('pmc-library-btn').onclick = (e) => {
    e.preventDefault();
    const frame = wp.media({ title: 'Select Image', multiple: false, library: { type: 'image' } });
    frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        loadSource(attachment.url, attachment.filename, { display_path: 'Media Library: ' + attachment.title });
    });
    frame.open();
};

presetSel.onchange = updateCanvasSize;

document.getElementById('mode-locked').onclick = function () {
    isLocked = true; this.classList.add('active');
    document.getElementById('mode-pillar').classList.remove('active');
    document.getElementById('pillarbox-controls').style.display = 'none';
    initCropper();
};

document.getElementById('mode-pillar').onclick = function () {
    isLocked = false; this.classList.add('active');
    document.getElementById('mode-locked').classList.remove('active');
    document.getElementById('pillarbox-controls').style.display = 'block';
    initCropper();
};

document.getElementById('pmc-mode').onchange = function () {
    document.getElementById('blur-wrap').style.display = (this.value === 'echo' ? 'block' : 'none');
    document.getElementById('color-picker-wrap').style.display = (this.value === 'custom' ? 'block' : 'none');
    update();
};

document.getElementById('pmc-blur').oninput = update;
document.getElementById('pmc-color').oninput = update;

document.getElementById('pmc-stock-btn').onclick = () => {
    document.getElementById('pmc-search-modal').style.display = 'block';
    document.getElementById('pmc-stock-query').focus();
};

document.getElementById('pmc-eyedropper-btn').onclick = function () {
    const btn = this;
    const cvs = document.getElementById('pmc-canvas');
    if (btn.classList.contains('pmc-eyedropper-active')) { cancelSelection(); return; }
    btn.classList.add('pmc-eyedropper-active');
    btn.textContent = 'Cancel';
    cvs.classList.add('selecting');
    cvs.onclick = (e) => {
        const rect = cvs.getBoundingClientRect();
        const x = Math.floor((e.clientX - rect.left) * (cvs.width / rect.width));
        const y = Math.floor((e.clientY - rect.top) * (cvs.height / rect.height));
        try {
            const pixel = ctx.getImageData(x, y, 1, 1).data;
            const hex = '#' + Array.from(pixel.slice(0, 3)).map(v => v.toString(16).padStart(2, '0')).join('');
            document.getElementById('pmc-color').value = hex;
            update();
        } catch (e) { console.error("Canvas tainted."); }
        cancelSelection();
    };
    function cancelSelection() {
        btn.classList.remove('pmc-eyedropper-active');
        btn.textContent = 'Pick';
        cvs.classList.remove('selecting');
        cvs.onclick = null;
    }
};

document.getElementById('pmc-save-btn').onclick = function () {
    const btn = this;
    const icon = btn.querySelector('.dashicons');
    btn.disabled = true;
    icon.classList.remove('dashicons-cloud-upload');
    icon.classList.add('dashicons-update');

    canvas.toBlob((blob) => {
        const fd = new FormData();
        fd.append('file', blob, (filenameInput.value || 'crop') + '.jpg');
        fd.append('description', currentMeta.description || '');

        fetch(pmc_vars.root + 'wp/v2/media', {
            method: 'POST',
            headers: { 'X-WP-Nonce': pmc_vars.nonce },
            body: fd
        })
            .then(r => r.json()).then(res => {
                icon.classList.remove('dashicons-update');
                icon.classList.add('dashicons-cloud-upload');
                btn.disabled = false;
                if (res.id) {
                    statusCont.innerHTML = `<div style="color:green; font-weight:600;">✅ Saved! <a href="${res.link}" target="_blank">View</a></div>`;
                    setTimeout(() => statusCont.innerHTML = '', 8000);
                } else { statusCont.innerHTML = '<div style="color:red;">Save failed.</div>'; }
            });
    }, 'image/jpeg', 0.92);
};

// Export to Global window for HTML event attributes
window.pmcStartNewSearch = function () {
    stockPage = 1; stockLoading = false;
    document.getElementById('pmc-stock-results').innerHTML = '<div id="pmc-stock-load-sentinel"></div>';
    const obs = new IntersectionObserver((es) => { if (es[0].isIntersecting && !stockLoading) fetchStock(); }, { root: document.getElementById('pmc-stock-results'), threshold: 0.1 });
    obs.observe(document.getElementById('pmc-stock-load-sentinel'));
    fetchStock();
};

function fetchStock() {
    const q = document.getElementById('pmc-stock-query').value; if (!q || stockLoading) return;
    stockLoading = true; const sen = document.getElementById('pmc-stock-load-sentinel');
    sen.innerHTML = '<div class="pmc-spinner"></div>';
    jQuery.post(pmc_vars.ajaxurl, { action: 'pmc_search_stock', query: q, provider: document.getElementById('pmc-stock-provider').value, page: stockPage }, function (res) {
        if (res.success && res.data.length) {
            res.data.forEach(item => {
                let i = document.createElement('img'); i.src = item.thumb;
                i.onclick = () => { document.getElementById('pmc-search-modal').style.display = 'none'; loadSource(item.full, q, item); };
                document.getElementById('pmc-stock-results').insertBefore(i, sen);
            });
            stockPage++; stockLoading = false; sen.innerHTML = '';
        } else { sen.textContent = "No more results."; stockLoading = false; }
    });
}

document.getElementById('pmc-stock-provider').onchange = () => {
    const query = document.getElementById('pmc-stock-query').value.trim();
    if (query) window.pmcStartNewSearch();
};
document.getElementById('pmc-stock-query').onkeypress = (e) => { if (e.key === 'Enter') window.pmcStartNewSearch(); };

// Final Run
updateCanvasSize();