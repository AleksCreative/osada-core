<?php
/** Serve the bundled source guide without copying files outside the plugin. */
if (!defined('ABSPATH')) {
    exit;
}

function osada_core_llms_content() {
    $path = OSADA_CORE_PATH . 'resources/llms.txt';
    if (!is_readable($path)) {
        return '';
    }
    $content = file_get_contents($path);
    if (false === $content) {
        return '';
    }
    // Keep links correct on staging sites, other domains and subdirectory installs.
    return str_replace('https://osadafabryczna.pl', untrailingslashit(home_url('/')), $content);
}

function osada_core_serve_llms($wp) {
    if ('llms.txt' !== $wp->request) {
        return;
    }
    $content = osada_core_llms_content();
    status_header('' === $content ? 404 : 200);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    // Revalidation lets plugin updates replace the guide without a long stale cache.
    header('Cache-Control: no-cache');
    if ('HEAD' !== ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
        echo $content;
    }
    exit;
}
// parse_request runs for unknown URLs too, so updates need no rewrite flush.
add_action('parse_request', 'osada_core_serve_llms', 0);
