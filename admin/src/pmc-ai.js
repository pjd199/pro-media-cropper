/**
 * Pro Media Cropper - AI Resize
 *
 * Handles the AI resize overlay: enter/exit, generate, accept.
 * Also owns the zoom helper used on the before/after panels.
 */

import { state, els } from './pmc-state.js';
import { loadSource } from './pmc-source.js';

// ── Zoom helper ───────────────────────────────────────────────────────────────

/**
 * Attach mouse-wheel + pinch zoom (and double-click reset) to a wrapper element.
 * @param {HTMLElement} wrap   - the scrollable/overflow container
 * @param {HTMLElement} image  - the img element inside it
 */
export function setupZoom(wrap, image) {
    let scale = 1, originX = 0, originY = 0;
    let isPinching = false, lastDist = 0;

    function applyTransform() {
        image.style.transform       = `scale(${scale})`;
        image.style.transformOrigin = `${originX}px ${originY}px`;
    }

    wrap.addEventListener('wheel', (e) => {
        e.preventDefault();
        const rect = wrap.getBoundingClientRect();
        originX = e.clientX - rect.left;
        originY = e.clientY - rect.top;
        scale   = Math.min(8, Math.max(1, scale * (e.deltaY < 0 ? 1.15 : 0.87)));
        if (scale === 1) { originX = 0; originY = 0; }
        applyTransform();
    }, { passive: false });

    wrap.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            isPinching = true;
            lastDist   = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
        }
    }, { passive: true });

    wrap.addEventListener('touchmove', (e) => {
        if (!isPinching || e.touches.length !== 2) return;
        const dist = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
        );
        scale    = Math.min(8, Math.max(1, scale * (dist / lastDist)));
        lastDist = dist;
        const rect  = wrap.getBoundingClientRect();
        originX = ((e.touches[0].clientX + e.touches[1].clientX) / 2) - rect.left;
        originY = ((e.touches[0].clientY + e.touches[1].clientY) / 2) - rect.top;
        applyTransform();
    }, { passive: true });

    wrap.addEventListener('touchend', () => { isPinching = false; });

    wrap.addEventListener('dblclick', () => {
        scale = 1; originX = 0; originY = 0;
        applyTransform();
    });
}

// ── Default prompt ────────────────────────────────────────────────────────────

export function aiDefaultPrompt() {
    const opt    = els.presetSel.selectedOptions[0];
    const width  = opt.dataset.w || state.exportW;
    const height = opt.dataset.h || state.exportH;
    return [
        `Resize and recompose this image to exactly ${width}×${height} pixels.`,
        'Preserve the original visual style, colours, typography, and composition.',
        'Keep important text clearly legible, especially titles, dates, and times.',
        'Minor secondary text may be removed if necessary for layout.',
        'Ensure all text on the output image is clearly visible.',
        'Do not add new design elements or borders.',
        'Preserve any QR code exactly as provided.',
    ].join(' ');
}

// ── Enter / exit ──────────────────────────────────────────────────────────────

export function enterAiMode() {
    if (state.aiMode) return;
    state.aiMode = true;

    // Snapshot source image: max 2000px on longest side, AVIF, max 2MB
    const MAX_DIM  = 2000;
    const MAX_SIZE = 2 * 1024 * 1024;
    const scale    = Math.min(1, MAX_DIM / Math.max(els.img.naturalWidth, els.img.naturalHeight));
    const snap     = document.createElement('canvas');
    snap.width     = Math.round(els.img.naturalWidth  * scale);
    snap.height    = Math.round(els.img.naturalHeight * scale);
    snap.getContext('2d').drawImage(els.img, 0, 0, snap.width, snap.height);

    (async () => {
        for (const quality of [0.85, 0.70, 0.55, 0.40]) {
            const blob = await new Promise(res => snap.toBlob(res, 'image/avif', quality));
            if (blob && (blob.size <= MAX_SIZE || quality === 0.40)) {
                state.aiSourceBlob = blob;
                break;
            }
        }
    })();

    // Populate panels
    els.aiSourceImg.src               = els.img.src;
    els.aiPromptInput.value           = aiDefaultPrompt();
    els.aiResultImg.style.display     = 'none';
    els.aiResultImg.src               = '';
    els.aiPlaceholder.style.display   = 'flex';
    els.aiPlaceholder.textContent     = 'Press Generate to create an AI-resized version';
    els.aiSpinner.style.display       = 'none';
    els.aiDims.textContent            = '';
    els.aiAccept.disabled             = true;

    if (state.cropper) state.cropper.disable();
    els.aiOverlay.style.display = 'flex';
    els.aiBtn.disabled          = true;
}

export function exitAiMode() {
    if (!state.aiMode) return;
    state.aiMode       = false;
    state.aiResultB64  = null;
    state.aiSourceBlob = null;

    els.aiOverlay.style.display = 'none';
    if (state.cropper) state.cropper.enable();
    els.aiBtn.disabled = false;
}

// ── Generate ──────────────────────────────────────────────────────────────────

const PROGRESS_STEPS = [
    { msg: 'Uploading image...',            delay: 0      },
    { msg: 'Analysing your image...',       delay: 6000   },
    { msg: 'Reading the layout...',         delay: 12000  },
    { msg: 'Identifying text...',           delay: 18000  },
    { msg: 'Recomposing for target size...', delay: 24000 },
    { msg: 'Fitting the content...',        delay: 30000  },
    { msg: 'Rendering text...',             delay: 36000  },
    { msg: 'Preserving key details...',     delay: 42000  },
    { msg: 'Refining the composition...',   delay: 49000  },
    { msg: 'Finishing up...',               delay: 57000  },
    { msg: 'Balancing the layout...',       delay: 67000  },
    { msg: 'Checking the output...',        delay: 78000  },
    { msg: 'Applying final touches...',     delay: 90000  },
    { msg: 'Almost there...',               delay: 103000 },
    { msg: 'Wrapping up...',               delay: 114000  },
];

export function runAiGenerate() {
    if (!state.aiSourceBlob) {
        alert('Source image not ready — please wait a moment and try again.');
        return;
    }

    const { aiGenerate, aiAccept, aiSpinner, aiPlaceholder,
            aiResultImg, aiDims, aiSpinnerMsg, aiPromptInput, presetSel } = els;

    const opt = presetSel.selectedOptions[0];
    const w   = parseInt(opt.dataset.w) || state.exportW;
    const h   = parseInt(opt.dataset.h) || state.exportH;

    aiGenerate.disabled           = true;
    aiAccept.disabled             = true;
    aiResultImg.style.display     = 'none';
    aiPlaceholder.style.display   = 'none';
    aiSpinner.style.display       = 'block';

    // Swap in the actual dimensions into the progress label
    const steps = PROGRESS_STEPS.map((s, i) =>
        i === 4 ? { ...s, msg: `Recomposing for ${w}×${h}...` } : s
    );

    const timers = steps.map(({ msg, delay }) =>
        setTimeout(() => { if (aiSpinnerMsg) aiSpinnerMsg.textContent = msg; }, delay)
    );
    const clearTimers = () => { timers.forEach(clearTimeout); timers.length = 0; };

    const reader = new FileReader();
    reader.onload = () => {
        const fd = new FormData();
        fd.append('image',  reader.result);
        fd.append('prompt', aiPromptInput.value || aiDefaultPrompt());

        fetch(pmc_vars.root + 'pmc/v1/ai-resize', {
            method:  'POST',
            headers: { 'X-WP-Nonce': pmc_vars.nonce },
            body:    fd,
            signal:  AbortSignal.timeout(120000),
        })
        .then(r => {
            if (!r.ok) return r.json().then(e => Promise.reject(e.error || 'Request failed'));
            return r.json();
        })
        .then(res => {
            clearTimers();
            aiSpinner.style.display   = 'none';
            aiGenerate.disabled       = false;

            state.aiResultB64         = res.b64;
            aiResultImg.src           = 'data:image/png;base64,' + state.aiResultB64;
            aiResultImg.style.display = 'block';
            aiDims.textContent        = `(${w}×${h})`;
            aiAccept.disabled         = false;
        })
        .catch(err => {
            clearTimers();
            aiSpinner.style.display     = 'none';
            aiGenerate.disabled         = false;
            aiPlaceholder.textContent   = '⚠ ' + (typeof err === 'string' ? err : 'Request failed or timed out.');
            aiPlaceholder.style.display = 'flex';
            console.error('PMC AI error', err);
        });
    };
    reader.readAsDataURL(state.aiSourceBlob);
}

// ── Accept result ─────────────────────────────────────────────────────────────

export function acceptAiResult() {
    if (!state.aiResultB64) return;
    const dataUrl  = 'data:image/png;base64,' + state.aiResultB64;
    const filename = (els.filenameInput.value || 'ai-result');

    exitAiMode();

    // Ensure locked mode before loading the AI result
    if (!state.isLocked) {
        els.modeLocked.click();
    }

    loadSource(dataUrl, filename, { isBlob: true });
}
