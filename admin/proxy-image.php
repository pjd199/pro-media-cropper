<?php

namespace ProMediaCropper;

if (!defined("ABSPATH")) {
    exit();
}

add_action('rest_api_init', function () {
    register_rest_route('pmc/v1', '/proxy-image', [
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\\pmc_proxy_image_handler',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args' => [
            'url' => [
                'required'          => true,
                'sanitize_callback' => 'esc_url_raw',
                'validate_callback' => function ($value) {
                    return filter_var($value, FILTER_VALIDATE_URL) !== false;
                },
            ],
        ],
    ]);
});

function pmc_proxy_image_handler(\WP_REST_Request $request) {
    $url  = $request->get_param('url');
    $host = parse_url($url, PHP_URL_HOST);

    $upload   = wp_upload_dir();
    $dir      = $upload['basedir'] . '/pmc-temp';
    $filename = 'pmc-' . md5($url);
    $file     = $dir . '/' . $filename;
    $file_url = $upload['baseurl'] . '/pmc-temp/' . $filename;

    wp_mkdir_p($dir);

    // Return cached file if under 1 hour old
    if (file_exists($file) && (time() - filemtime($file)) < HOUR_IN_SECONDS) {
        return rest_ensure_response(['url' => $file_url]);
    }

    // Fetch fresh copy
    $response = wp_remote_get($url, ['timeout' => 10, 'sslverify' => true]);
    if (is_wp_error($response)) {
        return new \WP_Error('fetch_failed', 'Failed to fetch image', ['status' => 502]);
    }

    $content_type = wp_remote_retrieve_header($response, 'content-type');
    if (strpos($content_type, 'image/') === false) {
        return new \WP_Error('not_an_image', 'Resource is not a valid image', ['status' => 422]);
    }

    file_put_contents($file, wp_remote_retrieve_body($response));

    return rest_ensure_response(['url' => $file_url]);
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

register_activation_hook(PMC_MAIN_FILE, function() {
    if ( ! wp_next_scheduled( 'pmc_cleanup_temp' ) ) {
        wp_schedule_event( time(), 'daily', 'pmc_cleanup_temp' );
    }
});

register_deactivation_hook(PMC_MAIN_FILE, function() {
    wp_clear_scheduled_hook('pmc_cleanup_temp');
});

add_action('pmc_cleanup_temp', function() {
    $upload = wp_upload_dir();
    $dir    = $upload['basedir'] . '/pmc-temp';
    
    if (!is_dir($dir)) {
        return;
    }

    $files = glob($dir . '/pmc-*');
    
    if (empty($files)) {
        return;
    }

    foreach ($files as $file) {
        // Ensure it's a file and older than 1 hour
        if (is_file($file) && (time() - filemtime($file)) > HOUR_IN_SECONDS) {
            unlink($file);
        }
    }
});
