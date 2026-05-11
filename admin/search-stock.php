<?php

namespace ProMediaCropper;

if (!defined("ABSPATH")) {
    exit();
}

add_action('rest_api_init', function () {
    register_rest_route('pmc/v1', '/search-stock', [
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\\pmc_search_stock_handler',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
        'args' => [
            'query' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    return !empty(trim($value));
                },
            ],
            'provider' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    return in_array($value, ['pixabay', 'unsplash', 'pexels'], true);
                },
            ],
            'page' => [
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($value) {
                    return $value >= 1;
                },
            ],
        ],
    ]);
});

function pmc_search_stock_handler(\WP_REST_Request $request) {
    $q  = $request->get_param('query');
    $p  = $request->get_param('provider');
    $pg = $request->get_param('page');

    $cache_key = 'pmc_v383_' . md5($p . '_' . $q . '_' . $pg);
    $cached    = get_transient($cache_key);
    if ($cached !== false) {
        return rest_ensure_response($cached);
    }

    $key = get_option('pmc_' . $p . '_key');
    if (!$key) {
        return new \WP_Error('missing_api_key', 'Missing API key for provider: ' . $p, ['status' => 500]);
    }

    $results = match ($p) {
        'pixabay'  => pmc_fetch_pixabay($q, $pg, $key),
        'unsplash' => pmc_fetch_unsplash($q, $pg, $key),
        'pexels'   => pmc_fetch_pexels($q, $pg, $key),
    };

    if (is_wp_error($results)) {
        return $results;
    }

    if (!empty($results)) {
        set_transient($cache_key, $results, DAY_IN_SECONDS);
        $tracker = get_option('pmc_cache_tracker', []);
        if (!in_array($cache_key, $tracker, true)) {
            $tracker[] = $cache_key;
            update_option('pmc_cache_tracker', $tracker, false);
        }
    }

    return rest_ensure_response($results);
}

// ── Provider fetchers ─────────────────────────────────────────────────────────

function pmc_fetch_pixabay(string $q, int $pg, string $key): array|\WP_Error {
    $resp = wp_remote_get(
        'https://pixabay.com/api/?key=' . $key
            . '&q=' . urlencode($q)
            . '&page=' . $pg
            . '&per_page=20&safesearch=true&image_type=photo'
    );

    if (is_wp_error($resp)) return $resp;

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $out  = [];
    foreach ($data['hits'] ?? [] as $i) {
        $out[] = [
            'thumb'  => $i['previewURL'],
            'full'   => $i['largeImageURL'],
            'author' => $i['user'],
            'source' => 'Pixabay',
            'desc'   => $i['tags'],
            'link'   => $i['pageURL'],
        ];
    }
    return $out;
}

function pmc_fetch_unsplash(string $q, int $pg, string $key): array|\WP_Error {
    $resp = wp_remote_get(
        'https://api.unsplash.com/search/photos?query=' . urlencode($q)
            . '&client_id=' . $key
            . '&page=' . $pg
            . '&per_page=20&content_filter=high'
    );

    if (is_wp_error($resp)) return $resp;

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $out  = [];
    foreach ($data['results'] ?? [] as $i) {
        $out[] = [
            'thumb'  => $i['urls']['thumb'],
            'full'   => $i['urls']['regular'],
            'author' => $i['user']['name'],
            'source' => 'Unsplash',
            'desc'   => $i['alt_description'] ?? 'Unsplash Photo',
            'link'   => $i['links']['html'],
        ];
    }
    return $out;
}

function pmc_fetch_pexels(string $q, int $pg, string $key): array|\WP_Error {
    $resp = wp_remote_get(
        'https://api.pexels.com/v1/search?query=' . urlencode($q)
            . '&page=' . $pg
            . '&per_page=20',
        ['headers' => ['Authorization' => $key]]
    );

    if (is_wp_error($resp)) return $resp;

    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $out  = [];
    foreach ($data['photos'] ?? [] as $i) {
        $out[] = [
            'thumb'  => $i['src']['tiny'],
            'full'   => $i['src']['large2x'],
            'author' => $i['photographer'],
            'source' => 'Pexels',
            'desc'   => $i['alt'] ?? 'Pexels Photo',
            'link'   => $i['url'],
        ];
    }
    return $out;
}