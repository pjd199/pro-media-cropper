/**
 * Pro Media Cropper - Modal API
 *
 * Explicit surface for the modal/tab integration.
 * Rather than leaking pmcDestroy / pmcExportAndSave onto window from within
 * pmcInit, this module owns those globals and imports the real implementations.
 */

import { state } from './pmc-state.js';
import { pmcExportAndSave } from './pmc-save.js';

/**
 * Destroy the active Cropper instance.
 * Call this when the modal tab is closed/hidden to free resources.
 */
window.pmcDestroy = function () {
    if (state.cropper) {
        state.cropper.destroy();
        state.cropper = null;
    }
};

/**
 * Export the current canvas and save it to the WP media library.
 * @returns {Promise<object>} WP REST API media response.
 */
window.pmcExportAndSave = pmcExportAndSave;
