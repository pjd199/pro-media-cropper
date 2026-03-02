<?php
/**
 * Plugin Name: Widescreen Media Upload and Crop
 * Description: Upload an image and crop to a widescreen 1920x1080 image
 * Version: 3.4.11
 * Author: Pete Dibdin
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_media_page('Pro Cropper', 'Pro Cropper', 'publish_posts', 'pro-media-cropper', 'pmc_render_page');
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'media_page_pro-media-cropper') return;
    wp_enqueue_style('cropper-css', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css');
    wp_enqueue_script('cropper-js', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js', array(), null, true);
    wp_enqueue_script('pdf-js', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js', array(), null, true);
    
    wp_localize_script('cropper-js', 'pmc_vars', array(
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest'),
    ));
});

function pmc_render_page() {
    ?>
    <style>
        .pmc-container { display: flex; gap: 20px; margin-top: 20px; max-width: 1300px; height: 82vh; }
        .pmc-main { flex: 1; background: #fff; border: 1px solid #ccd0d4; padding: 20px; display: flex; flex-direction: column; min-width: 0; }
        .pmc-sidebar { width: 340px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; overflow-y: auto; }
        .pmc-editor-wrapper { background: #111; flex: 1; margin-bottom: 15px; border-radius: 4px; overflow: hidden; position: relative; display: flex; align-items: center; justify-content: center;}
        #pmc-image { max-width: 100%; max-height: 100%; display: block; }
        .pmc-preview-box { width: 100%; aspect-ratio: 16/9; background: #000; border: 1px solid #ddd; margin-bottom: 10px; overflow: hidden; }
        #pmc-canvas { width: 100%; height: 100%; display: block; }
        .pmc-row { margin-bottom: 10px; }
        .pmc-row label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 11px; text-transform: uppercase; color: #64748b; }
        
        /* Toggle Switch Styling */
        .pmc-mode-toggle { display: flex; background: #f0f0f1; border-radius: 4px; padding: 4px; margin-bottom: 15px; border: 1px solid #ccd0d4; }
        .pmc-mode-btn { flex: 1; border: none; padding: 8px; cursor: pointer; font-size: 12px; font-weight: 600; border-radius: 3px; background: transparent; color: #64748b; transition: all 0.2s; }
        .pmc-mode-btn.active { background: #fff; color: #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        
        .pmc-btn-group { display: flex; flex-direction: column; gap: 10px; }
        .pmc-primary-btn { background: #2271b1; color: #fff; border: none; padding: 12px; border-radius: 4px; width: 100%; font-weight: bold; cursor: pointer; text-align: center; }
        .pmc-secondary-btn { background: #f6f7f7; color: #2271b1; border: 1px solid #2271b1; padding: 10px; border-radius: 4px; width: 100%; font-weight: 600; cursor: pointer; }
        #pmc-loading { position: absolute; inset: 0; background: rgba(255,255,255,0.9); display: none; flex-direction: column; align-items: center; justify-content: center; z-index: 10; font-weight:600;}
        
        /* Filename input styling */
        .pmc-filename-wrap { display: flex; align-items: center; background: #f6f7f7; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden; transition: border-color 0.15s; }
        .pmc-filename-wrap:focus-within { border-color: #2271b1; background: #fff; box-shadow: 0 0 0 1px #2271b1; }
        #pmc-filename { flex: 1; border: none; background: transparent; padding: 8px 10px; font-size: 13px; outline: none; color: #1d2327; min-width: 0; }
        #pmc-filename::placeholder { color: #a7aaad; }
        .pmc-filename-suffix { padding: 8px 10px; color: #64748b; font-size: 12px; font-weight: 600; border-left: 1px solid #ccd0d4; white-space: nowrap; background: #f0f0f1; }

        #pillarbox-controls { display: none; }
    </style>

    <div class="wrap">
        <h1>Pro Media Cropper</h1>
        <div class="pmc-container">
            <div class="pmc-main">
                <div class="pmc-editor-wrapper">
                    <div id="pmc-loading">Processing...</div>
                    <img id="pmc-image">
                </div>
                <div style="display:flex; gap:10px;">
                    <input type="file" id="pmc-file-input" accept=".pdf,.svg,.jpg,.jpeg,.png,.webp,.bmp" style="flex:1;">
                    <button id="pmc-reset-btn" class="pmc-secondary-btn" style="width:auto; padding: 0 20px; margin-top:0;">Reset Crop</button>
                </div>
            </div>
            <div class="pmc-sidebar">
                <label>Export Preview (1920x1080)</label>
                <div class="pmc-preview-box"><canvas id="pmc-canvas"></canvas></div>
                
                <div class="pmc-row">
                    <label>Crop Mode</label>
                    <div class="pmc-mode-toggle">
                        <button id="mode-locked" class="pmc-mode-btn active">Locked 16:9</button>
                        <button id="mode-pillar" class="pmc-mode-btn">Pillarbox</button>
                    </div>
                </div>

                <div id="pillarbox-controls">
                    <div class="pmc-row">
                        <label>Pillarbox Style</label>
                        <select id="pmc-mode" style="width: 100%;">
                            <option value="echo" selected>Echo Blur</option>
                            <option value="black">Solid Black</option>
                            <option value="white">Solid White</option>
                            <option value="custom">Custom Color</option>
                        </select>
                    </div>

                    <div class="pmc-row" id="color-picker-wrap" style="display:none;">
                        <label>Custom Color</label>
                        <div style="display:flex; gap:8px;">
                            <input type="color" id="pmc-color" value="#2271b1" style="width:50px; height: 40px; cursor: pointer; border:none; padding:2px;">
                            <button id="pmc-eyedropper-btn" class="pmc-secondary-btn" style="width:auto; padding: 0 12px; display:flex; align-items:center; gap:6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="16" height="16" fill="currentColor"><path d="M405.6 93.2L304 194.8L294.6 185.4C282.1 172.9 261.8 172.9 249.3 185.4C236.8 197.9 236.8 218.2 249.3 230.7L409.3 390.7C421.8 403.2 442.1 403.2 454.6 390.7C467.1 378.2 467.1 357.9 454.6 345.4L445.2 336L546.8 234.4C585.8 195.4 585.8 132.2 546.8 93.3C507.8 54.4 444.6 54.3 405.7 93.3zM119.4 387.3C104.4 402.3 96 422.7 96 443.9L96 486.3L69.4 526.2C60.9 538.9 62.6 555.8 73.4 566.6C84.2 577.4 101.1 579.1 113.8 570.6L153.7 544L196.1 544C217.3 544 237.7 535.6 252.7 520.6L362.1 411.2L316.8 365.9L207.4 475.3C204.4 478.3 200.3 480 196.1 480L160 480L160 443.9C160 439.7 161.7 435.6 164.7 432.6L274.1 323.2L228.8 277.9L119.4 387.3z"/></svg>
                                Pick from preview image
                            </button>
                        </div>
                    </div>
                    
                    <div class="pmc-row" id="blur-wrap">
                        <label>Blur Intensity</label>
                        <input type="range" id="pmc-blur" min="0" max="80" value="30" style="width: 100%;">
                    </div>
                </div>

                <div class="pmc-row">
                    <label>Output Filename</label>
                    <div class="pmc-filename-wrap">
                        <input type="text" id="pmc-filename" placeholder="upload a file to set filename">
                        <span class="pmc-filename-suffix">.jpg</span>
                    </div>
                </div>

                <div class="pmc-btn-group">
                    <button id="pmc-save-btn" class="pmc-primary-btn" disabled>Save to Media Library</button>
                </div>
                <div id="pmc-status" style="margin-top:15px; font-size: 12px;"></div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const fileInput = document.getElementById('pmc-file-input');
        const img = document.getElementById('pmc-image');
        const canvas = document.getElementById('pmc-canvas');
        const ctx = canvas.getContext('2d');
        const saveBtn = document.getElementById('pmc-save-btn');
        const resetBtn = document.getElementById('pmc-reset-btn');
        const loader = document.getElementById('pmc-loading');
        const modeSelect = document.getElementById('pmc-mode');
        const colorPicker = document.getElementById('pmc-color');
        const blurRange = document.getElementById('pmc-blur');
        const btnLocked = document.getElementById('mode-locked');
        const btnPillar = document.getElementById('mode-pillar');
        const pillarControls = document.getElementById('pillarbox-controls');
        const status = document.getElementById('pmc-status');
        const filenameInput = document.getElementById('pmc-filename');

        let cropper = null, originalName = 'image', isLocked = true;
        const W = 1920, H = 1080;
        canvas.width = W; canvas.height = H;

        function getExportName() {
            return (filenameInput.value.trim() || originalName) + '.jpg';
        }

        async function getPdfLib() {
            return new Promise((resolve) => {
                const check = () => {
                    const lib = window['pdfjs-dist/build/pdf'];
                    if (lib) { lib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js'; resolve(lib); }
                    else { setTimeout(check, 100); }
                };
                check();
            });
        }

        async function renderPdf(file) {
            const pdfjsLib = await getPdfLib();
            const pdf = await pdfjsLib.getDocument({data: await file.arrayBuffer()}).promise;
            const page = await pdf.getPage(1);
            const vp = page.getViewport({scale: 2.5});
            const c = document.createElement('canvas');
            c.height = vp.height; c.width = vp.width;
            await page.render({canvasContext: c.getContext('2d'), viewport: vp}).promise;
            return c.toDataURL('image/png');
        }

        fileInput.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            loader.style.display = 'flex';
            originalName = file.name.split('.')[0];
            filenameInput.value = originalName;
            try {
                let url = (file.type === 'application/pdf') ? await renderPdf(file) : URL.createObjectURL(file);
                if (cropper) cropper.destroy();
                img.src = url;
                img.onload = () => { initCropper(); saveBtn.disabled = false; loader.style.display = 'none'; };
            } catch (err) { alert(err.message); loader.style.display = 'none'; }
        };

        function initCropper() {
            if (cropper) cropper.destroy();
            cropper = new Cropper(img, {
                aspectRatio: isLocked ? 16/9 : NaN,
                viewMode: 1,
                ready: update,
                crop: update
            });
        }

        function update() {
            if (!cropper || !cropper.ready) return;
            const crop = cropper.getCroppedCanvas({imageSmoothingQuality: 'high'});
            if (!crop) return;

            ctx.clearRect(0, 0, W, H);
            
            if (!isLocked) {
                if (modeSelect.value === 'echo') {
                    ctx.save();
                    ctx.filter = `blur(${blurRange.value}px) brightness(0.6)`;
                    ctx.drawImage(crop, -150, -150, W + 300, H + 300);
                    ctx.restore();
                } else if (modeSelect.value === 'black') {
                    ctx.fillStyle = "#000000"; ctx.fillRect(0,0,W,H);
                } else if (modeSelect.value === 'white') {
                    ctx.fillStyle = "#FFFFFF"; ctx.fillRect(0,0,W,H);
                } else if (modeSelect.value === 'custom') {
                    ctx.fillStyle = customColor;
                    ctx.fillRect(0, 0, W, H);
                }
            } else {
                ctx.fillStyle = "#000000"; ctx.fillRect(0,0,W,H);
            }

            const r = Math.min(W / crop.width, H / crop.height);
            const nw = crop.width * r, nh = crop.height * r;
            ctx.drawImage(crop, (W - nw)/2, (H - nh)/2, nw, nh);
        }

        // Mode Switching Logic
        btnLocked.onclick = () => {
            isLocked = true;
            btnLocked.classList.add('active');
            btnPillar.classList.remove('active');
            pillarControls.style.display = 'none';
            initCropper();
        };

        btnPillar.onclick = () => {
            isLocked = false;
            btnPillar.classList.add('active');
            btnLocked.classList.remove('active');
            pillarControls.style.display = 'block';
            initCropper();
        };

        resetBtn.onclick = () => { if(cropper) initCropper(); };

        modeSelect.onchange = () => {
            document.getElementById('blur-wrap').style.display = (modeSelect.value === 'echo') ? 'block' : 'none';
            document.getElementById('color-picker-wrap').style.display = (modeSelect.value === 'custom') ? 'block' : 'none';
            update();
        };
        
        blurRange.oninput = update;

        let customColor = '#2271b1';
        let eyedropperActive = false;
        
        colorPicker.oninput = () => {
            customColor = colorPicker.value;
            update();
        };
        
        document.getElementById('pmc-eyedropper-btn').onclick = () => {
            eyedropperActive = true;
            canvas.style.cursor = 'crosshair';
        };
        
        canvas.onclick = (e) => {
            if (!eyedropperActive || modeSelect.value !== 'custom') return;
            const rect = canvas.getBoundingClientRect();
            const x = Math.floor((e.clientX - rect.left) * (W / rect.width));
            const y = Math.floor((e.clientY - rect.top) * (H / rect.height));
            const [r, g, b] = ctx.getImageData(x, y, 1, 1).data;
            customColor = '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
            colorPicker.value = customColor; // keep the swatch in sync
            eyedropperActive = false;
            canvas.style.cursor = 'default';
            update();
        };
        saveBtn.onclick = () => {
            saveBtn.disabled = true; status.textContent = "Saving...";
            canvas.toBlob((blob) => {
                const fd = new FormData();
                fd.append('file', blob, getExportName());
                fd.append('status', 'publish');
                fetch(pmc_vars.root + 'wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': pmc_vars.nonce }, body: fd })
                .then(r => r.json()).then(res => {
                    status.innerHTML = res.id ? "Saved! <a href='" + res.link + "' target='_blank'>View</a>" : "Failed.";
                    saveBtn.disabled = false;
                });
            }, 'image/jpeg', 0.95);
        };
    </script>
    <?php
}









