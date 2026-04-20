<?php
namespace ProMediaCropper;

/**
 * Renders the shared UI HTML template into a hidden script tag.
 * Called once per page load on any admin screen that loads pmc-admin-js.
 * Both the standalone page and the modal tab clone from this.
 */
add_action('admin_footer', function () {
    if (!wp_script_is('pmc-admin-js', 'enqueued')) {
        return;
    }
    echo '<script type="text/template" id="pmc-ui-template">';
    pmc_render_ui_html();
    echo '</script>';
});

function pmc_render_ui_html(): void { ?>
    <div style="display:flex; gap:4px; padding-bottom:12px;" class="pmc-source-bar">
        <button id="pmc-file-btn" class="components-button is-secondary"
                onclick="document.getElementById('pmc-file-input').click()">Upload File</button>
        <button id="pmc-paste-btn"   class="components-button is-secondary">Paste Image/URL</button>
        <button id="pmc-library-btn" class="components-button is-secondary">Use Media Library</button>
        <button id="pmc-stock-btn"   class="components-button is-secondary">Search Stock Images</button>
        <input type="file" id="pmc-file-input"
               accept=".pdf,.svg,.jpg,.jpeg,.png,.webp,.avif,.bmp" style="display:none;">
        <div id="pmc-attribution" class="pmc-attribution-line"></div>
    </div>

    <div class="pmc-container">
        <div class="pmc-main">
            <div class="pmc-editor-wrapper">
                <div id="pmc-loading">Processing…</div>
                <img id="pmc-image">
            </div>
        </div>
        <div class="pmc-sidebar">
            <label id="pmc-preview-label">Export Preview</label>
            <div class="pmc-preview-box"><canvas id="pmc-canvas"></canvas></div>

            <div class="pmc-row">
                <label>Aspect Ratio Preset</label>
                <select id="pmc-ratio-preset" style="width:100%;">
                    <option value="16:9"      data-w="1920" data-h="1080">Widescreen (16:9)</option>
                    <option value="1:1"       data-w="1080" data-h="1080">Square (1:1)</option>
                    <option value="4:5"       data-w="1080" data-h="1350">Instagram/Facebook Portrait (4:5)</option>
                    <option value="1.91:1"    data-w="1200" data-h="630" >Social Landscape (1.91:1)</option>
                    <option value="9:16"      data-w="1080" data-h="1920">Stories/Reels (9:16)</option>
                    <option value="X"         data-w="1600" data-h="900" >X / Twitter (16:9)</option>
                    <option value="Pinterest" data-w="1000" data-h="1500">Pinterest (2:3)</option>
                    <option value="YouTube"   data-w="1920" data-h="1080">YouTube Thumbnail (16:9)</option>
                    <option value="Photo"     data-w="1800" data-h="1200">Standard Photo 4×6 (3:2)</option>
                    <option value="custom"    data-w="" data-h="">
                        Custom <!-- JS fills in dimensions + label from pmc_vars -->
                    </option>
                </select>
            </div>

            <div class="pmc-row">
                <label>Crop Mode</label>
                <div class="pmc-mode-toggle">
                    <button id="mode-locked" class="pmc-mode-btn active">Locked Ratio</button>
                    <button id="mode-pillar" class="pmc-mode-btn">Pillarbox</button>
                </div>
            </div>

            <div id="pillarbox-controls" style="display:none;">
                <div class="pmc-row">
                    <label>Pillar Style</label>
                    <select id="pmc-mode" style="width:100%;">
                        <option value="echo">Echo Blur</option>
                        <option value="black">Black</option>
                        <option value="white">White</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div class="pmc-row" id="color-picker-wrap" style="display:none;">
                    <label>Custom Color</label>
                    <div style="display:flex; gap:8px;">
                        <input type="color" id="pmc-color" value="#2271b1" style="width:100%;">
                        <button id="pmc-eyedropper-btn" class="button" type="button">Pick</button>
                    </div>
                </div>
                <div class="pmc-row" id="blur-wrap">
                    <label>Blur Intensity</label>
                    <input type="range" id="pmc-blur" min="0" max="80" value="30" style="width:100%;">
                </div>
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

    <div id="pmc-search-modal" style="display:none;">
        <div class="pmc-modal-inner">
            <div style="display:flex; gap:10px;">
                <select id="pmc-stock-provider">
                    <option value="pixabay">Pixabay</option>
                    <option value="unsplash">Unsplash</option>
                    <option value="pexels">Pexels</option>
                </select>
                <input type="text" id="pmc-stock-query" style="flex:1" placeholder="Search keywords…">
                <button class="button button-primary"
                        onclick="window.pmcStartNewSearch()">Search</button>
                <button class="button"
                        onclick="this.closest('#pmc-search-modal').style.display='none'">Close</button>
            </div>
            <div id="pmc-stock-results" class="pmc-stock-grid">
                <div id="pmc-stock-load-sentinel">Type and press Enter</div>
            </div>
        </div>
    </div>
<?php }