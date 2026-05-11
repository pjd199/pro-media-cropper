/**
 * Pro Media Cropper - Event Bindings
 *
 * All onclick / onchange / oninput wiring in one place.
 * pmcInit() calls bindEvents() after elements and state are ready.
 */

import { state, els } from './pmc-state.js';
import { initCropper, update, updateCanvasSize } from './pmc-cropper.js';
import { loadSource, renderPdf } from './pmc-source.js';
import { enterAiMode, exitAiMode, runAiGenerate, acceptAiResult, setupZoom } from './pmc-ai.js';
import { startNewSearch } from './pmc-stock.js';
import { handleSaveClick } from './pmc-save.js';

export function bindEvents() {
    // ── Ratio preset ──────────────────────────────────────────────────────────
    els.presetSel.onchange = updateCanvasSize;

    // ── Lock / pillarbox mode ─────────────────────────────────────────────────
    els.modeLocked.onclick = function () {
        state.isLocked = true;
        this.classList.add('active');
        els.modePillar.classList.remove('active');
        els.pillarControls.style.display = 'none';
        initCropper();
    };

    els.modePillar.onclick = function () {
        state.isLocked = false;
        this.classList.add('active');
        els.modeLocked.classList.remove('active');
        els.pillarControls.style.display = 'block';
        initCropper();
    };

    // ── Pillarbox background controls ─────────────────────────────────────────
    els.modeSelect.onchange = function () {
        els.blurWrap.style.display         = this.value === 'echo'   ? 'block' : 'none';
        els.colorPickerWrap.style.display  = this.value === 'custom' ? 'block' : 'none';
        update();
    };

    els.blurInput.oninput  = update;
    els.colorInput.oninput = update;

    // ── File input ────────────────────────────────────────────────────────────
    els.fileInput.onchange = async (e) => {
        const f = e.target.files[0];
        if (!f) return;
        els.loader.style.display = 'flex';
        try {
            const url = f.type === 'application/pdf'
                ? await renderPdf(f)
                : URL.createObjectURL(f);
            loadSource(url, f.name, { display_path: 'Local File: ' + f.name });
        } catch {
            alert('Error loading file.');
            els.loader.style.display = 'none';
        }
        e.target.value = '';
    };

    // ── Paste ─────────────────────────────────────────────────────────────────
    els.pasteBtn.onclick = async () => {
        try {
            const clipboardItems = await navigator.clipboard.read();
            for (const item of clipboardItems) {
                const imageType = item.types.find(t => t.startsWith('image/'));
                if (imageType) {
                    loadSource(
                        URL.createObjectURL(await item.getType(imageType)),
                        'pasted-image-' + Date.now(),
                        { isBlob: true }
                    );
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
            if (fallback?.trim().startsWith('http')) {
                loadSource(fallback.trim(), 'pasted-url-' + Date.now(), { display_path: 'Pasted URL' });
            }
        }
    };

    // ── Media library ─────────────────────────────────────────────────────────
    els.libraryBtn.onclick = (e) => {
        e.preventDefault();
        const frame = wp.media({ title: 'Select Image', multiple: false, library: { type: 'image' } });
        frame.on('select', () => {
            const a = frame.state().get('selection').first().toJSON();
            loadSource(a.url, a.filename, { display_path: 'Media Library: ' + a.title });
        });
        frame.open();
    };

    // ── Stock search ──────────────────────────────────────────────────────────
    els.stockBtn.onclick = () => {
        els.searchModal.style.display = 'block';
        els.stockQuery.focus();
    };

    els.stockProvider.onchange = () => {
        if (els.stockQuery.value.trim()) startNewSearch();
    };

    els.stockQuery.onkeypress = (e) => {
        if (e.key === 'Enter') startNewSearch();
    };

    // ── Eyedropper ────────────────────────────────────────────────────────────
    els.eyedropperBtn.onclick = function () {
        const btn = this;
        if (btn.classList.contains('pmc-eyedropper-active')) { cancel(); return; }

        btn.classList.add('pmc-eyedropper-active');
        btn.textContent = 'Cancel';
        els.canvas.classList.add('selecting');

        els.canvas.onclick = (e) => {
            const rect = els.canvas.getBoundingClientRect();
            const x = Math.floor((e.clientX - rect.left) * (els.canvas.width  / rect.width));
            const y = Math.floor((e.clientY - rect.top)  * (els.canvas.height / rect.height));
            try {
                const pixel = els.ctx.getImageData(x, y, 1, 1).data;
                els.colorInput.value = '#' + Array.from(pixel.slice(0, 3))
                    .map(v => v.toString(16).padStart(2, '0')).join('');
                update();
            } catch { console.error('Canvas tainted.'); }
            cancel();
        };

        function cancel() {
            btn.classList.remove('pmc-eyedropper-active');
            btn.textContent = 'Pick';
            els.canvas.classList.remove('selecting');
            els.canvas.onclick = null;
        }
    };

    // ── Save ──────────────────────────────────────────────────────────────────
    els.saveBtn.onclick = handleSaveClick;

    // ── AI ────────────────────────────────────────────────────────────────────
    els.aiBtn.onclick      = enterAiMode;
    els.aiGenerate.onclick = runAiGenerate;
    els.aiAccept.onclick   = acceptAiResult;
    els.aiCancel.onclick   = exitAiMode;

    setupZoom(els.aiLeftWrap,  els.aiSourceImg);
    setupZoom(els.aiRightWrap, els.aiResultImg);
}

// ── Global alias for legacy HTML calls ────────────────────────────────────────
// e.g. onkeypress="pmcStartNewSearch()" in stock modal markup
window.pmcStartNewSearch = startNewSearch;
