<?php
// Development router only; refuse execution under the site's normal web server.
if ('cli-server' !== PHP_SAPI) {
    http_response_code(404);
    exit;
}
define('DISABLE_WP_CRON', true);
$_SERVER['HTTP_HOST'] = 'osadafabryczna-produkcja.local';
$_SERVER['SERVER_PORT'] = '80';
require dirname(__DIR__, 4) . '/wp-load.php';
// Simulate public launch only in this isolated process; never save the option.
add_filter('pre_option_osadafabryczna_access_enabled', '__return_zero');
wp();
do_action('template_redirect');
status_header(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Unrelated route';
