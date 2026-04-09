<?php

namespace ProMediaCropper;

if (!defined("ABSPATH")) {
    exit();
}

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
                "&page=$pg&per_page=20&safesearch=true&image_type=photo"
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
                "&client_id=$key&page=$pg&per_page=20&content_filter=high"
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
