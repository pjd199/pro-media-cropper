<?php

namespace ProMediaCropper;

if (!defined("ABSPATH")) {
    exit();
}

add_action('rest_api_init', function () {
    register_rest_route('pmc/v1', '/ai-resize', [
        'methods'             => 'POST',
        'callback'            => __NAMESPACE__ . '\pmc_ai_resize_handler',
        'permission_callback' => function () {
            return current_user_can('publish_posts');
        },
    ]);
});

function pmc_ai_resize_handler(\WP_REST_Request $request) {
    $api_key = get_option('pmc_openai_api_key') ?? '';

    if (empty($api_key)) {
        return new \WP_REST_Response(['error' => 'OpenAI API key not configured in settings.'], 500);
    }

    $image_data = $request->get_param('image') ?? '';
    $prompt     = sanitize_text_field($request->get_param('prompt') ?? '');

    if (empty($image_data)) {
        return new \WP_REST_Response(['error' => 'No image data received.'], 400);
    }

    $image_data = preg_replace('/^data:image\/\w+;base64,/', '', $image_data);
    $image_bin  = base64_decode($image_data);
    if ($image_bin === false) {
        return new \WP_REST_Response(['error' => 'Invalid image data.'], 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_buffer($finfo, $image_bin);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'])) {
        return new \WP_REST_Response(['error' => 'Unsupported image format.'], 400);
    }

    $ext = match($mime) {
        'image/jpeg' => '.jpg',
        'image/webp' => '.webp',
        default      => '.png',
    };
    $tmp = tempnam(sys_get_temp_dir(), 'pmc-ai-') . $ext;
    file_put_contents($tmp, $image_bin);

    try {
        set_time_limit(120);

        $symfonyClient = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 120]);
        $psr18Client   = new \Symfony\Component\HttpClient\Psr18Client($symfonyClient);

        $client = \OpenAI::factory()
            ->withApiKey($api_key)
            ->withHttpClient($psr18Client)
            ->make();

        $response = $client->images()->edit([
            'model'  => 'gpt-image-2',
            'image'  => fopen($tmp, 'r'),
            'prompt' => $prompt,
            'size'   => 'auto',
        ]);

        $b64 = $response->data[0]->b64_json ?? null;
        if (!$b64) {
            return new \WP_REST_Response(['error' => 'No image returned from OpenAI.'], 502);
        }

        return new \WP_REST_Response(['b64' => $b64], 200);

    } catch (\Exception $e) {
        return new \WP_REST_Response(['error' => 'OpenAI error: ' . $e->getMessage()], 502);
    } finally {
        if (file_exists($tmp)) unlink($tmp);
    }
}