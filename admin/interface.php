<?php

namespace ProMediaCropper;

if (!defined("ABSPATH")) {
    exit();
}

add_action("admin_menu", function () {
    add_media_page(
        'Pro Media Cropper',
        'Pro Media Cropper',
        'publish_posts',
        'pro-media-cropper',
        __NAMESPACE__.'\pmc_render_page'
    );
});

add_action("admin_enqueue_scripts", function ($hook) {
    if ($hook !== "media_page_pro-media-cropper") {
        return;
    }
    wp_enqueue_media();

    $base_url  = plugin_dir_url(PMC_MAIN_FILE) . 'admin/';
    $base_path = plugin_dir_path(PMC_MAIN_FILE) . 'admin/';

    $get_ver = function($rel_path) use ($base_path) {
        return file_exists($base_path . $rel_path) ? filemtime($base_path . $rel_path) : '1.0.0';
    };

    // Vendor scripts
    wp_enqueue_style("cropper-css", $base_url . 'css/vendor/cropper.min.css', [], $get_ver('css/vendor/cropper.min.css'));
    wp_enqueue_script("cropper-js", $base_url . 'js/vendor/cropper.min.js', ["jquery"], $get_ver('js/vendor/cropper.min.js'), true);
    wp_enqueue_script("pdf-js",     $base_url . 'js/vendor/pdf.min.mjs', [], $get_ver('js/vendor/pdf.min.mjs'), true);

    // Admin scripts
    wp_enqueue_style("pmc-admin-css", $base_url . 'css/cropper.css', [], $get_ver('css/cropper.css'));
    wp_enqueue_script(
        'pmc-admin-js', 
        $base_url . 'js/pmc-admin.mjs', 
        ['jquery', 'cropper-js'], 
        $get_ver('js/pmc-admin.mjs'), 
        true
    );

    // 4. Module Filter
    add_filter('script_loader_tag', function($tag, $handle) {
        if (in_array($handle, ['pmc-admin-js', 'pdf-js'])) {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }, 10, 2);

    wp_localize_script("cropper-js", "pmc_vars", [
        "nonce" => wp_create_nonce("wp_rest"),
        "ajaxurl" => admin_url("admin-ajax.php"),
        "root" => esc_url_raw(rest_url()),
        "pdf_worker_url" => plugin_dir_url(PMC_MAIN_FILE) . 'admin/js/vendor/pdf.worker.min.mjs',
        "default_ratio" => get_option("pmc_default_ratio", "16:9"),
    ]);
});

function pmc_render_page()
{
    ?>
    <div class="wrap" style="overflow: hidden;">
    <div class="wrap">
        <div style="display: flex; align-items: center; gap: 10px; padding-top: 10px; margin-bottom: 20px;">
            <h1 style="margin: 0; padding: 0; line-height: 1;">Pro Media Cropper</h1>
            
            <div style="display: flex; gap: 4px; margin-left: 10px;">
                <button id="pmc-file-btn" class="page-title-action" onclick="document.getElementById('pmc-file-input').click()">Upload File</button>
                <button id="pmc-paste-btn" class="page-title-action">Paste Image/URL</button>
                <button id="pmc-library-btn" class="page-title-action">Use Media Library</button>
                <button id="pmc-stock-btn" class="page-title-action">Search Stock Images</button>
            </div>
    
            <input type="file" id="pmc-file-input" accept=".pdf,.svg,.jpg,.jpeg,.png,.webp,.avif,.bmp" style="display:none;">
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

    </script>
    <?php
}