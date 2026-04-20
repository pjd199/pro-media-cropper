<?php
namespace ProMediaCropper;

add_action('admin_menu', function () {
    add_media_page(
        'Pro Media Cropper',
        'Pro Media Cropper',
        'publish_posts',
        'pro-media-cropper',
        __NAMESPACE__ . '\pmc_render_page'
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'media_page_pro-media-cropper') {
        return;
    }
    wp_enqueue_media();
    pmc_enqueue_assets();
});

function pmc_render_page(): void { ?>
    <div class="wrap" style="overflow:hidden;">
        <div style="display:flex; align-items:center; gap:10px; padding-top:10px; margin-bottom:20px;">
            <h1 style="margin:0; padding:0; line-height:1;">Pro Media Cropper</h1>
        </div>
        <div id="pmc-root">
            <?php pmc_render_ui_html(); ?>
        </div>
    </div>
<?php }