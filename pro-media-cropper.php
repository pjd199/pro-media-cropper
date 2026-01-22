<?php
/**
 * Plugin Name: Pro Media Cropper
 * Description: Crop PDF, SVG, BMP, WebP, PNG, JPG to 16:9. Save to WordPress Media Library or Download to Computer.
 * Version: 1.4
 * Author: Gemini Developer
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'pmc_register_menu');
function pmc_register_menu() {
    add_media_page('Pro Cropper', 'Pro Cropper', 'publish_posts', 'pro-media-cropper', 'pmc_render_page');
}

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
        .pmc-preview-box { width: 100%; aspect-ratio: 16/9; background: #000; border: 1px solid #ddd; margin-bottom: 15px; overflow: hidden; }
        #pmc-canvas { width: 100%; height: 100%; display: block; }
        .pmc-row { margin-bottom: 15px; }
        .pmc-row label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 11px; text-transform: uppercase; color: #64748b; }
        
        .pmc-btn-group { display: flex; flex-direction: column; gap: 10px; }
        .pmc-primary-btn { background: #2271b1; color: #fff; border: none; padding: 12px; border-radius: 4px; width: 100%; font-weight: bold; cursor: pointer; text-align: center; text-decoration: none; }
        .pmc-secondary-btn { background: #f6f7f7; color: #2271b1; border: 1px solid #2271b1; padding: 10px; border-radius: 4px; width: 100%; font-weight: 600; cursor: pointer; }
        .pmc-primary-btn:disabled, .pmc-secondary-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        #pmc-loading { position: absolute; inset: 0; background: rgba(255,255,255,0.9); display: none; flex-direction: column; align-items: center; justify-content: center; z-index: 10; }
        .spinner-ring { width: 30px; height: 30px; border: 3px solid #ddd; border-top: 3px solid #2271b1; border-radius: 50%; animation: pmc-spin 1s linear infinite; margin-bottom: 10px; }
        @keyframes pmc-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .pmc-hidden { display: none; }
    </style>

    <div class="wrap">
        <h1>Pro Media Cropper</h1>
        <div class="pmc-container">
            <div class="pmc-main">
                <div class="pmc-editor-wrapper">
                    <div id="pmc-loading">
                        <div class="spinner-ring"></div>
                        <div id="pmc-loading-text">Initializing...</div>
                    </div>
                    <img id="pmc-image">
                </div>
                <input type="file" id="pmc-file-input" accept=".pdf,.svg,.jpg,.jpeg,.png,.webp,.bmp">
            </div>

            <div class="pmc-sidebar">
                <label>Export Preview (1080p)</label>
                <div class="pmc-preview-box">
                    <canvas id="pmc-canvas"></canvas>
                </div>

                <div class="pmc-row">
                    <label>Pillarbox Mode</label>
                    <select id="pmc-mode" style="width: 100%;">
                        <option value="standard">Locked 16:9</option>
                        <option value="echo" selected>Echo Blur (Freeform)</option>
                    </select>
                </div>

                <div id="pmc-blur-row" class="pmc-row">
                    <label>Blur Intensity</label>
                    <input type="range" id="pmc-blur" min="0" max="80" value="30" style="width: 100%;">
                </div>

                <div class="pmc-btn-group">
                    <button id="pmc-save-btn" class="pmc-primary-btn" disabled>Save to Media Library</button>
                    <button id="pmc-dl-btn" class="pmc-secondary-btn" disabled>Download to Computer</button>
                </div>
                <div id="pmc-status" style="margin-top:15px; font-size: 12px; line-height: 1.4;"></div>
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
        const dlBtn = document.getElementById('pmc-dl-btn');
        const loader = document.getElementById('pmc-loading');
        const modeSelect = document.getElementById('pmc-mode');
        const blurRange = document.getElementById('pmc-blur');
        const status = document.getElementById('pmc-status');

        let cropper = null;
        let originalName = 'image';
        const W = 1920, H = 1080;
        canvas.width = W; canvas.height = H;

        async function getPdfLib() {
            return new Promise((resolve) => {
                const check = () => {
                    const lib = window['pdfjs-dist/build/pdf'];
                    if (lib) {
                        lib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                        resolve(lib);
                    } else { setTimeout(check, 100); }
                };
                check();
            });
        }

        fileInput.onchange = async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            loader.style.display = 'flex';
            originalName = file.name.split('.')[0];

            try {
                let sourceUrl;
                if (file.type === 'application/pdf') {
                    const pdfjsLib = await getPdfLib();
                    sourceUrl = await renderPdfToDataUrl(file, pdfjsLib);
                } else {
                    sourceUrl = URL.createObjectURL(file);
                }

                if (cropper) cropper.destroy();
                img.src = sourceUrl;
                img.onload = () => {
                    initCropper();
                    saveBtn.disabled = false;
                    dlBtn.disabled = false;
                    loader.style.display = 'none';
                };
            } catch (err) {
                alert("Error: " + err.message);
                loader.style.display = 'none';
            }
        };

        async function renderPdfToDataUrl(file, pdfjsLib) {
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({data: arrayBuffer}).promise;
            const page = await pdf.getPage(1);
            const viewport = page.getViewport({scale: 2.0});
            const tempCanvas = document.createElement('canvas');
            const tCtx = tempCanvas.getContext('2d');
            tempCanvas.height = viewport.height;
            tempCanvas.width = viewport.width;
            await page.render({canvasContext: tCtx, viewport: viewport}).promise;
            return tempCanvas.toDataURL('image/png');
        }

        function initCropper() {
            if (cropper) cropper.destroy();
            cropper = new Cropper(img, {
                aspectRatio: modeSelect.value === 'echo' ? NaN : 16/9,
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
            ctx.fillStyle = "#FFFFFF"; 
            ctx.fillRect(0,0,W,H);

            if (modeSelect.value === 'echo') {
                ctx.save();
                ctx.filter = `blur(${blurRange.value}px) brightness(0.6)`;
                ctx.drawImage(crop, -150, -150, W + 300, H + 300);
                ctx.restore();
                const r = Math.min(W / crop.width, H / crop.height);
                const nw = crop.width * r, nh = crop.height * r;
                ctx.drawImage(crop, (W - nw)/2, (H - nh)/2, nw, nh);
            } else {
                ctx.drawImage(crop, 0, 0, W, H);
            }
        }

        modeSelect.onchange = () => {
            document.getElementById('pmc-blur-row').classList.toggle('pmc-hidden', modeSelect.value !== 'echo');
            initCropper();
        };
        blurRange.oninput = () => update();

        // SAVE TO MEDIA LIBRARY
        saveBtn.onclick = () => {
            saveBtn.disabled = true;
            status.textContent = "Uploading to WordPress...";
            canvas.toBlob((blob) => {
                const formData = new FormData();
                formData.append('file', blob, originalName + "-1080p.jpg");
                formData.append('status', 'publish');
                fetch(pmc_vars.root + 'wp/v2/media', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': pmc_vars.nonce },
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.id) status.innerHTML = "<strong>Saved!</strong> <a href='" + res.link + "' target='_blank'>View in Library</a>";
                    else status.textContent = "Upload failed.";
                    saveBtn.disabled = false;
                });
            }, 'image/jpeg', 0.95);
        };

        // DOWNLOAD TO COMPUTER
        dlBtn.onclick = () => {
            const link = document.createElement('a');
            link.download = originalName + "-1080p.jpg";
            link.href = canvas.toDataURL('image/jpeg', 0.95);
            link.click();
            status.textContent = "Downloaded to computer.";
        };
    })();
    </script>
    <?php
}