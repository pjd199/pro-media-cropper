<?php

namespace ProMediaCropper;

if (!defined("ABSPATH")) {
    exit();
}

// Register the Settings Page
add_action("admin_menu", function () {
    add_options_page(
        'Pro Media Cropper Settings',
        'Pro Media Cropper',
        'manage_options',
        'pmc-settings',
        __NAMESPACE__ . '\pmc_settings_page_html'
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
            ? "https://api.unsplash.com/photos?client_id=$key&per_page=1&content_filter=high"
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
    $actually_cached = 0;
    $valid_keys = [];

    if (is_array($tracker)) {
        foreach ($tracker as $key) {
            // With Memcached, this call is extremely fast (micro-seconds)
            if (get_transient($key)) {
                $valid_keys[] = $key;
                $actually_cached++;
            }
        }
    }

    // Sync the database tracker with the reality of Memcached
    if (count($tracker) !== count($valid_keys)) {
        update_option("pmc_cache_tracker", $valid_keys);
    }

    $count = $actually_cached;

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
    <style>
        .wrap~.litespeed_icon.notice-success,
        .litespeed_icon.notice-success {
            display: none !important;
        }
    </style>
    <div class="wrap">
        <h1>Pro Media Cropper Settings</h1>
        <?php settings_errors("pmc_messages"); ?>
        <form method="post" action="options.php">
            <?php settings_fields("pmc_group"); ?>
            <table class="form-table">
                <tr>
                    <th>Default Aspect Ratio</th>
                    <td>
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
                    </td>
                </tr>
                <tr>
                    <th>Custom Dimensions (px)</th>
                    <td>
                        <input type="number" name="pmc_export_width" value="<?php echo esc_attr(
                                                                                get_option("pmc_export_width", "1920")
                                                                            ); ?>" class="small-text"> x
                        <input type="number" name="pmc_export_height" value="<?php echo esc_attr(
                                                                                    get_option("pmc_export_height", "1080")
                                                                                ); ?>" class="small-text">
                    </td>
                </tr>
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
                            <input type="password" id="pmc_<?php echo $slug; ?>_key" name="pmc_<?php echo $slug; ?>_key" value="<?php echo esc_attr(
                                                                                                                                    get_option("pmc_" . $slug . "_key")
                                                                                                                                ); ?>" class="regular-text pmc-api-input">
                            <button type="button"
                                class="button pmc-toggle-pw"
                                style="display: inline-flex; align-items: center; justify-content: center; height: 30px; width: 30px; padding: 0;"
                                title="Show/Hide Key">
                                <span class="dashicons dashicons-visibility" style="margin: 0; line-height: 1;"></span>
                            </button>
                            <button type="button" class="button pmc-test-btn" data-provider="<?php echo $slug; ?>">Test API</button>
                            <p class="description">
                                <a href="<?php echo $provider_links[$slug]; ?>" target="_blank" style="text-decoration:none;">View <?php echo $label; ?> License</a> |
                                <a href="<?php echo $provider_help[$slug]; ?>" target="_blank" style="text-decoration:none;">Get help with <?php echo $label; ?> API</a>
                            </p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th>Default Search Engine</th>
                    <td>
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
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr>
        <div style="display: flex; gap: 30px; margin-top: 20px;">
            <div class="card" style="flex: 1; max-width: 450px; padding: 15px; margin: 0;">
                <h3>Search Cache Management</h3>
                <p>The plugin caches stock image search results for 24 hours to improve performance.</p>
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
                $all_data = get_file_data(PMC_MAIN_FILE, [
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
            // Toggle Password Visibility
            $('.pmc-toggle-pw').on('click', function() {
                const btn = $(this);
                const input = btn.siblings('.pmc-api-input');
                const icon = btn.find('.dashicons');

                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                }
            });

            $('.pmc-test-btn').on('click', function() {
                const btn = $(this),
                    prov = btn.data('provider'),
                    key = $('#pmc_' + prov + '_key').val();
                btn.text('Testing...').prop('disabled', true);
                $.post(ajaxurl, {
                    action: 'pmc_test_api',
                    provider: prov,
                    key: key
                }, function(res) {
                    alert((res.success ? '✅ ' : '❌ ') + prov.toUpperCase() + ': ' + res.data);
                    btn.text('Test API').prop('disabled', false);
                });
            });
        });
    </script>
<?php
}
