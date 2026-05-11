/**
 * Pro Media Cropper - Source Loading
 *
 * Handles all the ways an image gets into the tool:
 * loadSource(), clearUI(), renderPdf().
 */

import * as pdfjsLib from 'pdfjs-dist';
import pdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url';
import { state, els } from './pmc-state.js';
import { initCropper } from './pmc-cropper.js';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;

// ── PDF rendering ─────────────────────────────────────────────────────────────

export async function renderPdf(file) {
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

// ── Clear UI ──────────────────────────────────────────────────────────────────

export function clearUI() {
    if (state.cropper) { state.cropper.destroy(); state.cropper = null; }
    if (state.currentBlobUrl?.startsWith('blob:')) URL.revokeObjectURL(state.currentBlobUrl);
    state.currentBlobUrl = null;

    els.img.src = '';
    els.img.classList.remove('loaded');
    state.currentMeta     = {};
    els.attrLine.innerHTML = '';
    els.ctx.clearRect(0, 0, state.exportW, state.exportH);
    els.saveBtn.disabled   = true;
    els.statusCont.innerHTML = '';
    els.aiBtn.disabled     = true;
}

// ── Load source ───────────────────────────────────────────────────────────────

export function loadSource(url, name, meta = {}) {
    if (!url) return;

    // Reset UI and state
    if (state.cropper) { 
        state.cropper.destroy(); 
        state.cropper = null; 
    }
    if (state.currentBlobUrl?.startsWith('blob:')) {
        URL.revokeObjectURL(state.currentBlobUrl);
    }

    els.img.classList.remove('loaded');
    els.loader.style.display = 'flex';
    els.saveBtn.disabled = true;

    // Standardize filename
    els.filenameInput.value = (name || 'image-' + Date.now())
        .toLowerCase()
        .replace(/\.[^/.]+$/, '')
        .replace(/\s+/g, '-');

    const isBlob = meta.isBlob || url.startsWith('blob:') || url.startsWith('data:');
    const isExternal = url.startsWith('http') && !url.includes(window.location.hostname);

    // Function to actually "push" the URL to the element
    const setImageSource = (src, useCors = false) => {
        if (useCors) {
            els.img.crossOrigin = 'anonymous';
        } else {
            els.img.removeAttribute('crossOrigin');
        }
        els.img.src = src;
    };

    if (isBlob) {
        setImageSource(url, false);
    } else if (isExternal) {
        fetch(`${pmc_vars.root}pmc/v1/proxy-image`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': pmc_vars.nonce,
            },
            body: JSON.stringify({ url }),
        })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            // Success: Use the proxy URL and enable CORS for Cropper
            setImageSource(data.url, true);
        })
        .catch(() => {
            alert('Proxy failed to fetch the external image.');
            els.loader.style.display = 'none';
        });
    } else {
        // Local image
        const cacheBuster = url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now();
        setImageSource(cacheBuster, true);
    }

    // Handlers only need to be defined once
    els.img.onload = () => {
        initCropper();
        els.img.classList.add('loaded');
        els.saveBtn.disabled = false;
        els.loader.style.display = 'none';
        els.aiBtn.disabled = false;
    };

    els.img.onerror = () => {
        // This triggers if setImageSource fails or proxy returns a bad image URL
        alert('Failed to load image. The source may be blocking external requests.');
        els.loader.style.display = 'none';
    };
}