<?php
/**
 * Plugin Name: Pro Media Cropper
 * Description: Precision cropping tool with advanced crop options and stock image search function.
 * Version: 3.9.3
 * Author: Pete Dibdin
 * GitHub Plugin URI: https://github.com/pjd199/pro-media-cropper
 * License: MIT
 */

if (!defined("ABSPATH")) {
    exit();
}

/* -------------------------------------------------------------------------
   1. SETTINGS & DEFAULT LOGIC
   ------------------------------------------------------------------------- */

add_action("admin_menu", function () {
    add_media_page(
        "Pro Media Cropper",
        "Pro Media Cropper",
        "publish_posts",
        "pro-media-cropper",
        "pmc_render_page"
    );
    add_options_page(
        "Pro Media Cropper Settings",
        "Pro Media Cropper",
        "manage_options",
        "pmc-settings",
        "pmc_settings_page_html"
    );
});

add_action("admin_init", function () {
    register_setting("pmc_group", "pmc_pixabay_key");
    register_setting("pmc_group", "pmc_unsplash_key");
    register_setting("pmc_group", "pmc_pexels_key");
    register_setting("pmc_group", "pmc_default_provider");
    register_setting("pmc_group", "pmc_export_width");
    register_setting("pmc_group", "pmc_export_height");
    register_setting("pmc_group", "pmc_default_ratio");

    if (
        isset($_POST["pmc_clear_cache"]) &&
        check_admin_referer("pmc_clear_cache_action")
    ) {
        $tracker = get_option("pmc_cache_tracker", []);
        if (is_array($tracker)) {
            foreach ($tracker as $key) {
                delete_transient($key);
            }
        }
        delete_option("pmc_cache_tracker");
        add_settings_error(
            "pmc_messages",
            "pmc_message",
            "Search cache cleared!",
            "updated"
        );
    }
});

add_action("wp_ajax_pmc_test_api", function () {
    $p = sanitize_text_field($_POST["provider"]);
    $key = sanitize_text_field($_POST["key"]);
    if (!$key) {
        wp_send_json_error("No key provided");
    }
    $url =
        $p === "pixabay"
            ? "https://pixabay.com/api/?key=$key&q=test"
            : ($p === "unsplash"
                ? "https://api.unsplash.com/photos?client_id=$key&per_page=1"
                : "https://api.pexels.com/v1/curated?per_page=1");
    $args = $p === "pexels" ? ["headers" => ["Authorization" => $key]] : [];
    $resp = wp_remote_get($url, $args);
    if (is_wp_error($resp)) {
        wp_send_json_error($resp->get_error_message());
    }
    $code = wp_remote_retrieve_response_code($resp);
    $code === 200
        ? wp_send_json_success("Connection Successful!")
        : wp_send_json_error("Failed with code: " . $code);
});

function pmc_settings_page_html()
{
    $def_provider = get_option("pmc_default_provider", "pixabay");
    $def_ratio = get_option("pmc_default_ratio", "16:9");
    $tracker = get_option("pmc_cache_tracker", []);
    $count = is_array($tracker) ? count($tracker) : 0;

    $provider_links = [
        "pixabay" => "https://pixabay.com/service/license/",
        "unsplash" => "https://unsplash.com/license",
        "pexels" => "https://www.pexels.com/license/",
    ];
    $provider_help = [
        "pixabay" => "https://pixabay.com/api/docs/",
        "unsplash" => "https://unsplash.com/developers/",
        "pexels" => "https://www.pexels.com/api/",
    ];
    ?>
    <div class="wrap">
        <h1>Pro Media Cropper Settings</h1>
        <?php settings_errors("pmc_messages"); ?>
        <form method="post" action="options.php">
            <?php settings_fields("pmc_group"); ?>
            <table class="form-table">
                <tr><th>Default Aspect Ratio</th><td>
                    <select name="pmc_default_ratio">
                        <option value="custom" <?php selected(
                            $def_ratio,
                            "custom"
                        ); ?>>Custom Settings (Dimensions below)</option>
                        <option value="16:9" <?php selected(
                            $def_ratio,
                            "16:9"
                        ); ?>>Widescreen (16:9)</option>
                        <option value="1:1" <?php selected(
                            $def_ratio,
                            "1:1"
                        ); ?>>Square (1:1)</option>
                        <option value="4:5" <?php selected(
                            $def_ratio,
                            "4:5"
                        ); ?>>Instagram/Facebook Portrait (4:5)</option>
                        <option value="1.91:1" <?php selected(
                            $def_ratio,
                            "1.91:1"
                        ); ?>>Social Landscape (1.91:1)</option>
                        <option value="9:16" <?php selected(
                            $def_ratio,
                            "9:16"
                        ); ?>>Stories/Reels (9:16)</option>
                        <option value="X" <?php selected(
                            $def_ratio,
                            "X"
                        ); ?>>X (Twitter) (16:9)</option>
                        <option value="Pinterest" <?php selected(
                            $def_ratio,
                            "Pinterest"
                        ); ?>>Pinterest (2:3)</option>
                        <option value="YouTube" <?php selected(
                            $def_ratio,
                            "YouTube"
                        ); ?>>YouTube Thumbnail (16:9)</option>
                        <option value="Photo" <?php selected(
                            $def_ratio,
                            "Photo"
                        ); ?>>Standard Photo (4x6) (3:2)</option>
                    </select>
                </td></tr>
                <tr><th>Custom Dimensions (px)</th><td>
                    <input type="number" name="pmc_export_width" value="<?php echo esc_attr(
                        get_option("pmc_export_width", "1920")
                    ); ?>" class="small-text"> x 
                    <input type="number" name="pmc_export_height" value="<?php echo esc_attr(
                        get_option("pmc_export_height", "1080")
                    ); ?>" class="small-text">
                </td></tr>
                <?php foreach (
                    [
                        "pixabay" => "Pixabay",
                        "unsplash" => "Unsplash",
                        "pexels" => "Pexels",
                    ]
                    as $slug => $label
                ): ?>
                <tr>
                    <th><?php echo $label; ?> API Key</th>
                    <td>
                        <input type="text" id="pmc_<?php echo $slug; ?>_key" name="pmc_<?php echo $slug; ?>_key" value="<?php echo esc_attr(
    get_option("pmc_" . $slug . "_key")
); ?>" class="regular-text">
                        <button type="button" class="button pmc-test-btn" data-provider="<?php echo $slug; ?>">Test API</button>
                        <p class="description">
                            <a href="<?php echo $provider_links[
                                $slug
                            ]; ?>" target="_blank" style="text-decoration:none;">View <?php echo $label; ?> License</a> | 
                            <a href="<?php echo $provider_help[
                                $slug
                            ]; ?>" target="_blank" style="text-decoration:none;">Get help with <?php echo $label; ?> API</a>
                        </p>
                    </td>
                </tr>
                <?php endforeach; ?>
                                <tr><th>Default Search Engine</th><td>
                    <select name="pmc_default_provider">
                        <option value="pixabay" <?php selected(
                            $def_provider,
                            "pixabay"
                        ); ?>>Pixabay</option>
                        <option value="unsplash" <?php selected(
                            $def_provider,
                            "unsplash"
                        ); ?>>Unsplash</option>
                        <option value="pexels" <?php selected(
                            $def_provider,
                            "pexels"
                        ); ?>>Pexels</option>
                    </select>
                </td></tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr>
        <div style="display: flex; gap: 30px; margin-top: 20px;">
            <div class="card" style="flex: 1; max-width: 450px; padding: 15px; margin: 0;">
                <h3>Search Cache Management</h3>
                <p>The plugin caches stock search results for 24 hours to improve performance.</p>
                <p><strong>Currently Cached:</strong> <?php echo $count; ?> search result pages.</p>
                <form method="post" action="">
                    <?php wp_nonce_field("pmc_clear_cache_action"); ?>
                    <input type="submit" name="pmc_clear_cache" class="button" value="Wipe Search Cache" <?php disabled(
                        $count,
                        0
                    ); ?>>
                </form>
            </div>

            <div class="card" style="flex: 1; max-width: 450px; padding: 15px; margin: 0;">
                <h3>Plugin Information</h3>
                <?php
                if (!function_exists("get_plugin_data")) {
                    require_once ABSPATH . "wp-admin/includes/plugin.php";
                }

                // Explicitly define the extra headers we want to grab
                $extra_headers = [
                    "License" => "License",
                    "GitHubPluginURI" => "GitHub Plugin URI",
                ];

                // Pass the extra headers as the third argument
                $plugin_data = get_plugin_data(__FILE__, false, false);

                // For custom headers, we use get_file_data for more reliable extraction
                $all_data = get_file_data(__FILE__, [
                    "Version" => "Version",
                    "License" => "License",
                    "GitHub" => "GitHub Plugin URI",
                    "Desc" => "Description",
                ]);
                ?>
                <p><strong>Version:</strong> <?php echo esc_html(
                    $all_data["Version"]
                ); ?></p>
                <p><strong>License:</strong> <?php echo esc_html(
                    $all_data["License"]
                ); ?></p>
                <p><strong>Support:</strong> <a href="<?php echo esc_url(
                    $all_data["GitHub"]
                ); ?>" target="_blank">GitHub Repository</a></p>
                <p class="description"><?php echo esc_html(
                    $all_data["Desc"]
                ); ?></p>
            </div>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('.pmc-test-btn').on('click', function() {
            const btn = $(this), prov = btn.data('provider'), key = $('#pmc_' + prov + '_key').val();
            btn.text('Testing...').prop('disabled', true);
            $.post(ajaxurl, { action: 'pmc_test_api', provider: prov, key: key }, function(res) {
                alert((res.success ? '✅ ' : '❌ ') + prov.toUpperCase() + ': ' + res.data);
                btn.text('Test API').prop('disabled', false);
            });
        });
    });
    </script>
    <?php
}

/* -------------------------------------------------------------------------
   2. UI & CROPPER LOGIC
   ------------------------------------------------------------------------- */

add_action("admin_enqueue_scripts", function ($hook) {
    if ($hook !== "media_page_pro-media-cropper") {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style(
        "cropper-css",
        "https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css"
    );
    wp_enqueue_script(
        "cropper-js",
        "https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js",
        ["jquery"],
        null,
        true
    );
    wp_enqueue_script(
        "pdf-js",
        "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js",
        [],
        "3.11.174",
        true
    );
    wp_localize_script("cropper-js", "pmc_vars", [
        "nonce" => wp_create_nonce("wp_rest"),
        "ajaxurl" => admin_url("admin-ajax.php"),
        "root" => esc_url_raw(rest_url()),
        "default_ratio" => get_option("pmc_default_ratio", "16:9"),
    ]);
});

function pmc_render_page()
{
    ?>
    <style>
        .pmc-container { display: flex; gap: 20px; margin-top: 10px; height: calc(100vh - 120px); min-height: 550px; }
        .pmc-main { flex: 1; background: #fff; border: 1px solid #ccd0d4; padding: 20px; display: flex; flex-direction: column; min-width: 0; }
        .pmc-sidebar { width: 320px; background: #fff; border: 1px solid #ccd0d4; padding: 15px; display: flex; flex-direction: column; }
        .pmc-editor-wrapper { background: #111; flex: 1; margin-bottom: 15px; border-radius: 4px; overflow: hidden; position: relative; display: flex; align-items: center; justify-content: center;}
        #pmc-image { max-width: 100%; max-height: 100%; display: block; opacity: 0; transition: opacity 0.3s; }
        #pmc-image.loaded { opacity: 1; }
        .pmc-preview-box { width: 100%; height: 160px; background: #000; border: 1px solid #ddd; margin-bottom: 10px; overflow: hidden; flex-shrink: 0; }
        #pmc-canvas { width: 100%; height: 100%; object-fit: contain; display: block; }
        .pmc-row { margin-bottom: 12px; flex-shrink: 0; }
        .pmc-row label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 11px; text-transform: uppercase; color: #64748b; }
        .pmc-mode-toggle { display: flex; background: #f0f0f1; border-radius: 4px; padding: 4px; border: 1px solid #ccd0d4; }
        .pmc-mode-btn { flex: 1; border: none; padding: 6px; cursor: pointer; font-size: 11px; font-weight: 600; border-radius: 3px; background: transparent; color: #64748b; }
        .pmc-mode-btn.active { background: #fff; color: #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .pmc-filename-wrap { display: flex; background: #f6f7f7; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden; }
        #pmc-filename { flex: 1; border: none; background: transparent; padding: 8px; outline: none; width: 100%; box-sizing: border-box; }
        #pmc-loading { position: absolute; inset: 0; background: rgba(255,255,255,0.9); display: none; align-items: center; justify-content: center; z-index: 10; font-weight: 600; }
        #pmc-search-modal { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); }
        .pmc-modal-inner { background:#fff; width:90%; max-width:1100px; margin:40px auto; padding:20px; border-radius:8px; height:80vh; display:flex; flex-direction:column; }
        .pmc-stock-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:15px; overflow-y:auto; flex: 1; margin-top: 15px; }
        .pmc-stock-grid img { width:100%; aspect-ratio:3/2; object-fit:cover; cursor:pointer; border-radius:4px; }
        .pmc-attribution-line {
                font-size: 13px;
                color: #64748b;
                font-style: italic;
            }
            
            /* Ensure buttons look like native WP header buttons */
            .page-title-action {
                cursor: pointer;
            }
        .pmc-spinner { border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; width: 20px; height: 20px; animation: spin 1s linear infinite; margin: 10px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .pmc-eyedropper-active {
            background: #2271b1 !important;
            color: #fff !important;
            border-color: #135e96 !important;
            box-shadow: 0 0 5px rgba(34, 113, 177, 0.7);
            animation: pmc-pulse 1.5s infinite;
        }
        
        @keyframes pmc-pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        /* Ensure the canvas shows the crosshair clearly */
        #pmc-canvas.selecting {
            cursor: crosshair !important;
            outline: 2px dashed #2271b1;
        }
        .pmc-filename-wrap {
            display: flex;
            background: #fff; /* White background for the whole bar */
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        
        .pmc-filename-wrap:focus-within {
            border-color: #2271b1; /* Highlight border when typing */
        }
        
        #pmc-filename {
            flex: 1;
            border: none !important; /* Remove internal borders */
            box-shadow: none !important;
            padding: 8px 12px;
        }
        
        .pmc-save-icon-btn {
            background: #f0f0f1;
            border: none;
            border-left: 1px solid #ccd0d4;
            padding: 0 15px;
            cursor: pointer;
            color: #2271b1;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }
        
        .pmc-save-icon-btn:hover:not(:disabled) {
            background: #2271b1;
            color: #fff;
        }
        
        .pmc-save-icon-btn:disabled {
            color: #a7aaad;
            cursor: not-allowed;
            background: #f6f7f7;
        }
        
        /* Make the icon slightly larger */
        .pmc-save-icon-btn .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }

        #pmc-stock-load-sentinel {
            grid-column: 1 / -1; /* Stretch across the whole grid row */
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            clear: both;
        }
    </style>

    <div class="wrap" style="overflow: hidden;">
    <div class="wrap">
        <div style="display: flex; align-items: center; gap: 10px; padding-top: 10px; margin-bottom: 20px;">
            <h1 style="margin: 0; padding: 0; line-height: 1;">Pro Media Cropper</h1>
            
            <div style="display: flex; gap: 4px; margin-left: 10px;">
                <button id="pmc-file-btn" class="page-title-action" onclick="document.getElementById('pmc-file-input').click()">Upload File</button>
                <button id="pmc-library-btn" class="page-title-action">Use Media Library</button>
                <button id="pmc-stock-btn" class="page-title-action">Search Stock Images</button>
            </div>
    
            <input type="file" id="pmc-file-input" accept=".pdf,.svg,.jpg,.jpeg,.png,.webp,.bmp" style="display:none;">
            <div id="pmc-attribution" class="pmc-attribution-line"></div>
        </div>
        <div class="pmc-container">
            <div class="pmc-main">
                <div class="pmc-editor-wrapper"><div id="pmc-loading">Processing...</div><img id="pmc-image"></div>
            </div>
            <div class="pmc-sidebar">
                <label id="pmc-preview-label">Export Preview</label>
                <div class="pmc-preview-box"><canvas id="pmc-canvas"></canvas></div>
                <div class="pmc-row"><label>Aspect Ratio Preset</label>
                    <select id="pmc-ratio-preset" style="width:100%;">
                        <option value="custom" data-w="<?php echo get_option(
                            "pmc_export_width",
                            "1920"
                        ); ?>" data-h="<?php echo get_option(
    "pmc_export_height",
    "1080"
); ?>">Custom Settings (<?php echo get_option("pmc_export_width", "1920") .
    "x" .
    get_option("pmc_export_height", "1080"); ?>)</option>
                        <option value="16:9" data-w="1920" data-h="1080">Widescreen (16:9)</option>
                        <option value="1:1" data-w="1080" data-h="1080">Square (1:1)</option>
                        <option value="4:5" data-w="1080" data-h="1350">Instagram/Facebook Portrait (4:5)</option>
                        <option value="1.91:1" data-w="1200" data-h="630">Social Landscape (1.91:1)</option>
                        <option value="9:16" data-w="1080" data-h="1920">Stories/Reels (9:16)</option>
                        <option value="X" data-w="1600" data-h="900">X (Twitter) (16:9)</option>
                        <option value="Pinterest" data-w="1000" data-h="1500">Pinterest (2:3)</option>
                        <option value="YouTube" data-w="1920" data-h="1080">YouTube Thumbnail (16:9)</option>
                        <option value="Photo" data-w="1800" data-h="1200">Standard Photo (4x6) (3:2)</option>
                    </select>
                </div>
                <div class="pmc-row"><label>Crop Mode</label>
                    <div class="pmc-mode-toggle">
                        <button id="mode-locked" class="pmc-mode-btn active">Locked Ratio</button>
                        <button id="mode-pillar" class="pmc-mode-btn">Pillarbox</button>
                    </div>
                </div>
                <div id="pillarbox-controls" style="display:none;">
                    <div class="pmc-row"><label>Pillar Style</label><select id="pmc-mode" style="width: 100%;"><option value="echo">Echo Blur</option><option value="black">Black</option><option value="white">White</option><option value="custom">Custom</option></select></div>
                    
                    <div class="pmc-row" id="color-picker-wrap" style="display:none;">
    <label>Custom Color</label>
    <div style="display:flex; gap:8px;">
        <input type="color" id="pmc-color" value="#2271b1" style="width:100%;">
        <button id="pmc-eyedropper-btn" class="button" type="button">Pick</button>
    </div>
</div>
                    
                    <div class="pmc-row" id="blur-wrap"><label>Blur Intensity</label><input type="range" id="pmc-blur" min="0" max="80" value="30" style="width: 100%;"></div>
                </div>
                
                <div class="pmc-filename-wrap">
    <input type="text" id="pmc-filename" placeholder="filename">
    <button id="pmc-save-btn" class="pmc-save-icon-btn" title="Save to Library" disabled>
        <span class="dashicons dashicons-cloud-upload"></span>
    </button>
</div>
             <div id="pmc-status-container"></div>   
            </div>
        </div>
    </div>

    <div id="pmc-search-modal"><div class="pmc-modal-inner"><div style="display:flex; gap:10px;"><select id="pmc-stock-provider"><option value="pixabay">Pixabay</option><option value="unsplash">Unsplash</option><option value="pexels">Pexels</option></select><input type="text" id="pmc-stock-query" style="flex:1" placeholder="Search keywords..."><button class="button button-primary" onclick="window.pmcStartNewSearch()">Search</button><button class="button" onclick="document.getElementById('pmc-search-modal').style.display='none'">Close</button></div><div id="pmc-stock-results" class="pmc-stock-grid"><div id="pmc-stock-load-sentinel">Type and press Enter</div></div></div></div>

    <script>
    (function() {
        const initUI = () => {
            if (typeof pmc_vars === 'undefined' || typeof Cropper === 'undefined') { setTimeout(initUI, 50); return; }
            const canvas = document.getElementById('pmc-canvas'), ctx = canvas.getContext('2d');
            const img = document.getElementById('pmc-image'), loader = document.getElementById('pmc-loading');
            const filenameInput = document.getElementById('pmc-filename'), statusCont = document.getElementById('pmc-status-container'), attrLine = document.getElementById('pmc-attribution'), presetSel = document.getElementById('pmc-ratio-preset');
            let cropper = null, isLocked = true, currentMeta = {}, stockPage = 1, stockLoading = false, currentBlobUrl = null;
            let exportW, exportH;

            presetSel.value = pmc_vars.default_ratio;

            function updateCanvasSize() {
                const opt = presetSel.selectedOptions[0];
                exportW = parseInt(opt.dataset.w); exportH = parseInt(opt.dataset.h);
                canvas.width = exportW; canvas.height = exportH;
                document.getElementById('pmc-preview-label').textContent = `Export Preview (${exportW}x${exportH})`;
                if (cropper) initCropper();
            }
            updateCanvasSize();

            async function renderPdf(file) {
                const pdfjsLib = window['pdfjs-dist/build/pdf'];
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                const pdf = await pdfjsLib.getDocument({data: await file.arrayBuffer()}).promise;
                const page = await pdf.getPage(1), vp = page.getViewport({scale: 3.0});
                const c = document.createElement('canvas'); c.height = vp.height; c.width = vp.width;
                await page.render({canvasContext: c.getContext('2d'), viewport: vp}).promise;
                return c.toDataURL('image/png');
            }

            function clearUI() {
                if (cropper) { cropper.destroy(); cropper = null; }
                if (currentBlobUrl && currentBlobUrl.startsWith('blob:')) URL.revokeObjectURL(currentBlobUrl);
                currentBlobUrl = null; img.src = ''; img.classList.remove('loaded');
                filenameInput.value = ''; currentMeta = {}; attrLine.innerHTML = ''; ctx.clearRect(0, 0, exportW, exportH);
                document.getElementById('pmc-save-btn').disabled = true;
                statusCont.innerHTML = '';
            }

            function loadSource(url, name, meta = {}) {
                if(!url) return; clearUI(); currentBlobUrl = url; loader.style.display = 'flex';
                filenameInput.value = name.toLowerCase().replace(/\.[^/.]+$/, "").replace(/\s+/g, '-');
                if (meta.author && meta.source) {
                    meta.description = `Photo by ${meta.author} via ${meta.source}. Original: ${meta.link || 'N/A'}`;
                    attrLine.innerHTML = `Stock photo by ${meta.author} on <a href="${meta.link}" target="_blank">${meta.source}</a>`;
                } else if (meta.display_path) {
                    attrLine.textContent = meta.display_path;
                    meta.description = `Source: ${meta.display_path}`;
                }
                currentMeta = meta;
                img.crossOrigin = "anonymous"; img.src = url.includes('data:') || url.includes('blob:') ? url : url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now();
                img.onload = () => { initCropper(); img.classList.add('loaded'); document.getElementById('pmc-save-btn').disabled = false; loader.style.display = 'none'; };
            }

            function initCropper() {
                if (cropper) cropper.destroy();
                cropper = new Cropper(img, { aspectRatio: isLocked ? exportW/exportH : NaN, viewMode: 1, crop: update });
            }

            function update() {
                if (!cropper || !cropper.ready) return;
                const crop = cropper.getCroppedCanvas({imageSmoothingQuality: 'high'});
                ctx.clearRect(0, 0, exportW, exportH);
                if (!isLocked) {
                    const mode = document.getElementById('pmc-mode').value;
                    if (mode === 'echo') {
                        ctx.save(); ctx.filter = `blur(${document.getElementById('pmc-blur').value}px) brightness(0.6)`;
                        ctx.drawImage(crop, -100, -100, exportW + 200, exportH + 200); ctx.restore();
                    } else {
                        ctx.fillStyle = (mode === 'white') ? "#FFF" : (mode === 'custom' ? document.getElementById('pmc-color').value : "#000");
                        ctx.fillRect(0,0,exportW,exportH);
                    }
                } else { ctx.fillStyle = "#000"; ctx.fillRect(0,0,exportW,exportH); }
                const r = Math.min(exportW / crop.width, exportH / crop.height);
                const nw = crop.width * r, nh = crop.height * r;
                ctx.drawImage(crop, (exportW - nw)/2, (exportH - nh)/2, nw, nh);
            }

            document.getElementById('pmc-file-input').onchange = async (e) => {
                const f = e.target.files[0]; if(!f) return;
                loader.style.display = 'flex';
                try {
                    const url = (f.type === 'application/pdf') ? await renderPdf(f) : URL.createObjectURL(f);
                    loadSource(url, f.name, { display_path: 'Local File: ' + f.name });
                } catch (err) { alert('Error loading file.'); loader.style.display = 'none'; }
                e.target.value = '';
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
            document.getElementById('mode-locked').onclick = function() { isLocked = true; this.classList.add('active'); document.getElementById('mode-pillar').classList.remove('active'); document.getElementById('pillarbox-controls').style.display='none'; initCropper(); };
            document.getElementById('mode-pillar').onclick = function() { isLocked = false; this.classList.add('active'); document.getElementById('mode-locked').classList.remove('active'); document.getElementById('pillarbox-controls').style.display='block'; initCropper(); };
            document.getElementById('pmc-mode').onchange = function() { document.getElementById('blur-wrap').style.display = (this.value==='echo'?'block':'none'); document.getElementById('color-picker-wrap').style.display = (this.value==='custom'?'block':'none'); update(); };
            document.getElementById('pmc-blur').oninput = update;
            document.getElementById('pmc-color').oninput = update;
            document.getElementById('pmc-stock-btn').onclick = () => { document.getElementById('pmc-search-modal').style.display='block'; document.getElementById('pmc-stock-query').focus(); };
                document.getElementById('pmc-eyedropper-btn').onclick = function() {
                    const btn = this;
                    const cvs = document.getElementById('pmc-canvas');
                    
                    // Toggle state: If already selecting, cancel it
                    if (btn.classList.contains('pmc-eyedropper-active')) {
                        cancelSelection();
                        return;
                    }
                
                    // Enter Selection Mode
                    btn.classList.add('pmc-eyedropper-active');
                    btn.textContent = 'Cancel';
                    cvs.classList.add('selecting');
                
                    cvs.onclick = (e) => {
                        const rect = cvs.getBoundingClientRect();
                        
                        // Map mouse coordinates to internal canvas resolution
                        const x = Math.floor((e.clientX - rect.left) * (cvs.width / rect.width));
                        const y = Math.floor((e.clientY - rect.top) * (cvs.height / rect.height));
                        
                        try {
                            const pixel = ctx.getImageData(x, y, 1, 1).data;
                            const hex = '#' + Array.from(pixel.slice(0, 3))
                                .map(v => v.toString(16).padStart(2, '0'))
                                .join('');
                            
                            document.getElementById('pmc-color').value = hex;
                            update(); // Trigger the pillarbox redraw
                        } catch (e) {
                            console.error("Security error: Canvas might be tainted by cross-origin image.");
                        }
                        
                        cancelSelection();
                    };
                
                    function cancelSelection() {
                        btn.classList.remove('pmc-eyedropper-active');
                        btn.textContent = 'Pick';
                        cvs.classList.remove('selecting');
                        cvs.onclick = null;
                    }
                };
            document.getElementById('pmc-save-btn').onclick = function() {
                const btn = this;
                const icon = btn.querySelector('.dashicons'); // Target the icon
                
                btn.disabled = true;
                icon.classList.remove('dashicons-cloud-upload');
                icon.classList.add('dashicons-update'); // Spinning update icon
                
                canvas.toBlob((blob) => {
                    const fd = new FormData();
                    fd.append('file', blob, (filenameInput.value || 'crop') + '.jpg');
                    fd.append('description', currentMeta.description || '');
                    fetch(pmc_vars.root + 'wp/v2/media', { method: 'POST', headers: { 'X-WP-Nonce': pmc_vars.nonce }, body: fd })
                    .then(r => r.json()).then(res => {
                        const icon = btn.querySelector('.dashicons');
                        icon.classList.remove('dashicons-update');
                        icon.classList.add('dashicons-cloud-upload');
                        btn.disabled = false; // Re-enable for the next use
                        if (res.id) {
                            statusCont.innerHTML = `<div style="color:green; font-weight:600;">✅ Saved! <a href="${res.link}" target="_blank">View</a></div>`;
                            setTimeout(() => statusCont.innerHTML = '', 8000);
                        } else { statusCont.innerHTML = '<div style="color:red;">Save failed.</div>'; }
                    });
                }, 'image/jpeg', 0.92);
            };

            window.pmcStartNewSearch = function() {
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
                jQuery.post(pmc_vars.ajaxurl, { action: 'pmc_search_stock', query: q, provider: document.getElementById('pmc-stock-provider').value, page: stockPage }, function(res) {
                    if (res.success && res.data.length) {
                        res.data.forEach(item => {
                            let i = document.createElement('img'); i.src = item.thumb;
                            i.onclick = () => { document.getElementById('pmc-search-modal').style.display='none'; loadSource(item.full, q, item); };
                            document.getElementById('pmc-stock-results').insertBefore(i, sen);
                        });
                        stockPage++; stockLoading = false; sen.innerHTML = '';
                    } else { sen.textContent = "No more results."; stockLoading = false; }
                });
            }
            document.getElementById('pmc-stock-provider').onchange = () => {
                const query = document.getElementById('pmc-stock-query').value.trim();
                if (query) {
                    window.pmcStartNewSearch();
                }
            };
            document.getElementById('pmc-stock-query').onkeypress = (e) => { if(e.key==='Enter') window.pmcStartNewSearch(); };
        };
        initUI();
    })();
    </script>
    <?php
}

/* -------------------------------------------------------------------------
   3. AJAX SEARCH LOGIC
   ------------------------------------------------------------------------- */
add_action("wp_ajax_pmc_search_stock", function () {
    $q = sanitize_text_field($_POST["query"]);
    $p = sanitize_text_field($_POST["provider"]);
    $pg = intval($_POST["page"]);
    $cache_key = "pmc_v383_" . md5($p . "_" . $q . "_" . $pg);
    if ($cached = get_transient($cache_key)) {
        wp_send_json_success($cached);
    }
    $results = [];
    $key = get_option("pmc_" . $p . "_key");
    if (!$key) {
        wp_send_json_error("Missing API Key");
    }

    if ($p === "pixabay") {
        $resp = wp_remote_get(
            "https://pixabay.com/api/?key=$key&q=" .
                urlencode($q) .
                "&page=$pg&per_page=20"
        );
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        foreach ($data["hits"] ?? [] as $i) {
            $results[] = [
                "thumb" => $i["previewURL"],
                "full" => $i["largeImageURL"],
                "author" => $i["user"],
                "source" => "Pixabay",
                "desc" => $i["tags"],
                "link" => $i["pageURL"],
            ];
        }
    } elseif ($p === "unsplash") {
        $resp = wp_remote_get(
            "https://api.unsplash.com/search/photos?query=" .
                urlencode($q) .
                "&client_id=$key&page=$pg&per_page=20"
        );
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        foreach ($data["results"] ?? [] as $i) {
            $results[] = [
                "thumb" => $i["urls"]["thumb"],
                "full" => $i["urls"]["regular"],
                "author" => $i["user"]["name"],
                "source" => "Unsplash",
                "desc" => $i["alt_description"] ?? "Unsplash Photo",
                "link" => $i["links"]["html"],
            ];
        }
    } elseif ($p === "pexels") {
        $resp = wp_remote_get(
            "https://api.pexels.com/v1/search?query=" .
                urlencode($q) .
                "&page=$pg&per_page=20",
            ["headers" => ["Authorization" => $key]]
        );
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        foreach ($data["photos"] ?? [] as $i) {
            $results[] = [
                "thumb" => $i["src"]["tiny"],
                "full" => $i["src"]["large2x"],
                "author" => $i["photographer"],
                "source" => "Pexels",
                "desc" => $i["alt"] ?? "Pexels Photo",
                "link" => $i["url"],
            ];
        }
    }
    if (!empty($results)) {
        set_transient($cache_key, $results, DAY_IN_SECONDS);
        $t = get_option("pmc_cache_tracker", []);
        if (!in_array($cache_key, $t)) {
            $t[] = $cache_key;
            update_option("pmc_cache_tracker", $t, false);
        }
    }
    wp_send_json_success($results);
});
