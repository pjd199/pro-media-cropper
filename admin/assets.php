<?php
namespace ProMediaCropper;

/**
 * Single function used by both the standalone page and the media-tab enqueue hooks.
 */
function pmc_enqueue_assets(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $dir_url  = plugin_dir_url(PMC_MAIN_FILE);
    $dir_path = plugin_dir_path(PMC_MAIN_FILE);

    $js_bundle = 'admin/dist/pmc-admin.js';
    if (file_exists($dir_path . $js_bundle)) {
        wp_register_script(
            'pmc-admin-js',
            $dir_url . $js_bundle,
            ['jquery'],
            filemtime($dir_path . $js_bundle),
            true
        );
        wp_enqueue_script('pmc-admin-js');
    }

    $tab_js = 'admin/dist/pmc-media-tab.js';
    if (file_exists($dir_path . $tab_js)) {
        wp_register_script(
            'pmc-media-tab',
            $dir_url . $tab_js,
            ['pmc-admin-js', 'media-editor', 'wp-dom-ready'],
            filemtime($dir_path . $tab_js),
            true
        );
        wp_enqueue_script('pmc-media-tab');
    }

    $css_bundle = 'admin/dist/pro-media-cropper.css';
    if (file_exists($dir_path . $css_bundle)) {
        wp_enqueue_style(
            'pmc-admin-css',
            $dir_url . $css_bundle,
            [],
            filemtime($dir_path . $css_bundle)
        );
    }

    wp_localize_script('pmc-admin-js', 'pmc_vars', [
        'nonce'         => wp_create_nonce('wp_rest'),
        'ajaxurl'       => admin_url('admin-ajax.php'),
        'root'          => esc_url_raw(rest_url()),
        'default_ratio' => get_option('pmc_default_ratio', '16:9'),
        'save_exact'    => (bool) get_option('pmc_save_exact_dimensions', false),
        'export_width'  => get_option('pmc_export_width',  '1920'),
        'export_height' => get_option('pmc_export_height', '1080'),
    ]);
}

// Block editor + classic media modal
add_action('enqueue_block_editor_assets', __NAMESPACE__ . '\pmc_enqueue_assets');
add_action('wp_enqueue_media',            __NAMESPACE__ . '\pmc_enqueue_assets');