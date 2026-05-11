/**
 * Pro Media Cropper - Main Entry Point
 *
 * Thin orchestrator: initialise state/elements, boot the canvas, bind events.
 * All real logic lives in the imported modules.
 */

import './pmc-admin.css';
import { initElements, els } from './pmc-state.js';
import { updateCanvasSize } from './pmc-cropper.js';
import { bindEvents } from './pmc-events.js';
import './pmc-modal-api.js'; // registers window.pmcDestroy / window.pmcExportAndSave

function pmcInit(rootEl = document) {
    // 1. Build element cache
    initElements(rootEl);

    // 2. Patch the "custom" preset option with values from pmc_vars
    const customOpt = els.presetSel?.querySelector('option[value="custom"]');
    if (customOpt) {
        customOpt.dataset.w   = pmc_vars.export_width;
        customOpt.dataset.h   = pmc_vars.export_height;
        customOpt.textContent = `Custom (${pmc_vars.export_width}×${pmc_vars.export_height})`;
    }

    // 3. Set the default ratio selection before computing canvas size
    els.presetSel.value = pmc_vars.default_ratio;

    // 4. Size the canvas and wire all event listeners
    updateCanvasSize();
    bindEvents();
}

// ── Boot ──────────────────────────────────────────────────────────────────────

// Standalone admin page — init immediately
if (document.getElementById('pmc-canvas')) {
    pmcInit(document);
}

// Expose for modal tab (modal calls pmcInit(modalRootEl) after injecting HTML)
window.pmcInit = pmcInit;
