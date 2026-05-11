/**
 * Pro Media Cropper - Save / Export
 *
 * Handles both the manual save button and the programmatic pmcExportAndSave()
 * used by the modal integration.
 */

import { state, els } from './pmc-state.js';

// ── Shared upload logic ───────────────────────────────────────────────────────

/**
 * Encode the current canvas as a JPEG and POST it to the WP media REST endpoint.
 * @returns {Promise<object>} The WP REST API response object.
 */
export function pmcExportAndSave() {
    return new Promise((resolve, reject) => {
        els.canvas.toBlob((blob) => {
            const fd = new FormData();
            fd.append('file',        blob, (els.filenameInput.value || 'crop') + '.jpg');
            fd.append('description', state.currentMeta.description || '');

            fetch(pmc_vars.root + 'wp/v2/media', {
                method:  'POST',
                headers: { 'X-WP-Nonce': pmc_vars.nonce },
                body:    fd,
            })
            .then(r => r.json())
            .then(resolve)
            .catch(reject);
        }, 'image/jpeg', 0.92);
    });
}

// ── Save button handler ───────────────────────────────────────────────────────

export function handleSaveClick() {
    const btn  = els.saveBtn;
    const icon = btn.querySelector('.dashicons');

    btn.disabled = true;
    icon.classList.replace('dashicons-cloud-upload', 'dashicons-update');

    pmcExportAndSave()
        .then(res => {
            icon.classList.replace('dashicons-update', 'dashicons-cloud-upload');
            btn.disabled = false;

            if (res.id) {
                els.statusCont.innerHTML = `
                    <div style="color:green;font-weight:600;">
                        ✅ Saved! <a href="${res.link}" target="_blank">View</a>
                    </div>`;
                setTimeout(() => { els.statusCont.innerHTML = ''; }, 8000);
            } else {
                els.statusCont.innerHTML = '<div style="color:red;">Save failed.</div>';
            }
        })
        .catch(() => {
            icon.classList.replace('dashicons-update', 'dashicons-cloud-upload');
            btn.disabled = false;
            els.statusCont.innerHTML = '<div style="color:red;">Save failed.</div>';
        });
}
