<?php

namespace ProMediaCropper;

if (!defined("ABSPATH")) {
    exit();
}

// Register the proxy action for logged-in users
add_action('wp_ajax_pmc_proxy_image', function () {
    // 1. Security Check: Only allow authorized users (e.g., editors/admins)
    if (!current_user_can('edit_posts')) {
        wp_die('Unauthorized');
    }

    $url = isset($_GET['url']) ? esc_url_raw($_GET['url']) : '';

    if (!$url) {
        wp_die('No URL provided');
    }

    // 2. Fetch the image using WordPress's secure HTTP API
    $response = wp_remote_get($url, array(
        'timeout'   => 10,
        'sslverify' => true // Ensure we verify SSL for security
    ));

    if (is_wp_error($response)) {
        wp_die('Failed to fetch image');
    }

    $content_type = wp_remote_retrieve_header($response, 'content-type');
    $image_data   = wp_remote_retrieve_body($response);

    // 3. Verify it's actually an image
    if (strpos($content_type, 'image/') === false) {
        wp_die('Resource is not a valid image');
    }

    // 4. Output the image with correct headers
    header("Content-Type: $content_type");
    header("Access-Control-Allow-Origin: *"); // Allows your JS to read it
    header("Cache-Control: max-age=86400"); // Cache for 24 hours
    echo $image_data;
    exit;
});
