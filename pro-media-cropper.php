<?php

/**
 * Plugin Name: Pro Media Cropper
 * Description: Precision cropping tool with advanced crop options and stock image search function.
 * Version: 3.10.1
 * Author: Pete Dibdin
 * GitHub Plugin URI: https://github.com/pjd199/pro-media-cropper
 * License: MIT
 */

namespace ProMediaCropper;

if (!defined("ABSPATH")) {
    exit();
}

define('PMC_MAIN_FILE', __FILE__);

// Load Composer Autoloader (GitHub Actions will build this)
if (file_exists(plugin_dir_path(PMC_MAIN_FILE) . 'vendor/autoload.php')) {
    require_once plugin_dir_path(PMC_MAIN_FILE) . 'vendor/autoload.php';
}

if (is_admin()) {
    require_once plugin_dir_path(PMC_MAIN_FILE) . 'admin/settings.php';
    require_once plugin_dir_path(PMC_MAIN_FILE) . 'admin/search-stock.php';
    require_once plugin_dir_path(PMC_MAIN_FILE) . 'admin/proxy-image.php';
    require_once plugin_dir_path(PMC_MAIN_FILE) . 'admin/user-interface.php';
    require_once plugin_dir_path(PMC_MAIN_FILE) . 'admin/assets.php';
    require_once plugin_dir_path(PMC_MAIN_FILE) . 'admin/admin-page.php';
}

// Check for latest updates from GitHub
$updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/pjd199/pro-media-cropper/',
    PMC_MAIN_FILE,
    'pro-media-cropper'
);
$updateChecker->setBranch('main');
$updateChecker->getVcsApi()->enableReleaseAssets('/pro-media-cropper-\d+\.\d+\.\d+.\.zip($|[?&#])/i');