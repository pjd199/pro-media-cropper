<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

/* dont' remove any settings */

/* Delete the temp folder and all files inside */
$upload = wp_upload_dir();
$dir    = $upload['basedir'] . '/pmc-temp';

if (is_dir($dir)) {
    $files = glob($dir . '/*'); 
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($dir); // Remove the directory itself
}