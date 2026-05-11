/**
 * Pro Media Cropper - Cropper & Canvas
 *
 * Owns initCropper(), update(), and updateCanvasSize().
 */

import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { state, els } from './pmc-state.js';

// ── Canvas size ───────────────────────────────────────────────────────────────

export function updateCanvasSize() {
    const opt = els.presetSel.selectedOptions[0];
    state.exportW = parseInt(opt.dataset.w);
    state.exportH = parseInt(opt.dataset.h);
    els.canvas.width  = state.exportW;
    els.canvas.height = state.exportH;
    els.previewLabel.textContent = `Export Preview (${state.exportW}x${state.exportH})`;
    if (state.cropper) initCropper();
}

// ── Cropper init ──────────────────────────────────────────────────────────────

export function initCropper() {
    if (state.cropper) state.cropper.destroy();

    state.cropper = new Cropper(els.img, {
        aspectRatio: state.isLocked ? state.exportW / state.exportH : NaN,
        viewMode: 1,

        ready() {
            const imageData  = state.cropper.getImageData();
            const canvasData = state.cropper.getCanvasData();

            const imageRatio  = imageData.naturalWidth / imageData.naturalHeight;
            const targetRatio = state.exportW / state.exportH;
            const tolerance   = 0.01;
            const ratiosMatch = Math.abs(imageRatio - targetRatio) < tolerance;

            // If already the correct aspect ratio, select the entire image
            if (ratiosMatch && state.isLocked) {
                state.cropper.setCropBoxData({
                    left:   canvasData.left,
                    top:    canvasData.top,
                    width:  canvasData.width,
                    height: canvasData.height,
                });
            }

            update();
        },

        cropmove: update,
        crop:     update,
    });
}

// ── Canvas update ─────────────────────────────────────────────────────────────

export function update() {
    if (!state.cropper || !state.cropper.ready) return;
    const data = state.cropper.getData();
    if (Math.floor(data.width) <= 0 || Math.floor(data.height) <= 0) return;

    const crop = state.cropper.getCroppedCanvas({
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
    });
    if (!crop) return;

    const { canvas, ctx, previewLabel, modeSelect, blurInput, colorInput } = els;
    const { exportW, exportH } = state;

    if (state.isLocked) {
        let finalW, finalH;
        if (pmc_vars.save_exact) {
            finalW = exportW;
            finalH = exportH;
        } else {
            let ratio = Math.min(exportW / crop.width, exportH / crop.height);
            if (ratio > 1) ratio = 1;
            finalW = Math.round(crop.width * ratio);
            finalH = Math.round(finalW * (exportH / exportW)); // derive to avoid rounding drift
        }

        if (canvas.width !== finalW || canvas.height !== finalH) {
            canvas.width  = finalW;
            canvas.height = finalH;
            previewLabel.textContent = `Export Preview (${finalW}x${finalH})`;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(crop, 0, 0, finalW, finalH);

    } else {
        let canvasW, canvasH;
        if (pmc_vars.save_exact) {
            canvasW = exportW;
            canvasH = exportH;
        } else {
            const scaleToFitCrop = Math.max(crop.width / exportW, crop.height / exportH);
            const canvasScale    = Math.min(scaleToFitCrop, 1);
            canvasW = Math.round(exportW * canvasScale);
            canvasH = Math.round(exportH * canvasScale);
        }

        if (canvas.width !== canvasW || canvas.height !== canvasH) {
            canvas.width  = canvasW;
            canvas.height = canvasH;
            previewLabel.textContent = `Export Preview (${canvasW}x${canvasH})`;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const mode = modeSelect.value;
        if (mode === 'echo') {
            ctx.save();
            ctx.filter = `blur(${blurInput.value}px) brightness(0.6)`;
            ctx.drawImage(crop, -20, -20, canvasW + 40, canvasH + 40);
            ctx.restore();
        } else {
            ctx.fillStyle = mode === 'white'  ? '#FFF'
                          : mode === 'custom' ? colorInput.value
                          : '#000';
            ctx.fillRect(0, 0, canvasW, canvasH);
        }

        const imgScale = Math.min(
            canvasW / crop.width,
            canvasH / crop.height,
            pmc_vars.save_exact ? Infinity : 1
        );
        const drawW = Math.round(crop.width  * imgScale);
        const drawH = Math.round(crop.height * imgScale);
        ctx.drawImage(
            crop,
            Math.round((canvasW - drawW) / 2),
            Math.round((canvasH - drawH) / 2),
            drawW, drawH
        );
    }
}
