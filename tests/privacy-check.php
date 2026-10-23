<?php
/** Real WordPress integration; no writes to users, options, posts or rewrites. */
if (PHP_SAPI !== 'cli') {
    exit;
}
define('DISABLE_WP_CRON', true);
$_SERVER['HTTP_HOST'] = 'osadafabryczna-produkcja.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname(__DIR__, 4) . '/wp-load.php';
add_filter('pre_option_osadafabryczna_access_enabled', '__return_zero');
$count = 0;
function privacy_check($condition, $label) {
    global $count;
    $count++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
}

$sitemaps = wp_sitemaps_get_server();
privacy_check(null === $sitemaps->registry->get_provider('users'), 'users provider removed');
privacy_check($sitemaps->registry->get_provider('posts') instanceof WP_Sitemaps_Posts, 'posts provider retained');
$index = $sitemaps->renderer->get_sitemap_index_xml($sitemaps->index->get_sitemap_list());
privacy_check(!str_contains($index, 'wp-sitemap-users-'), 'author URLs absent from sitemap index');
privacy_check(!str_contains($index, '<?xml-stylesheet'), 'index does not use XSLT');
privacy_check((bool) simplexml_load_string($index), 'index remains valid XML');
$posts = $sitemaps->registry->get_provider('posts');
$xml = $sitemaps->renderer->get_sitemap_xml($posts->get_url_list(1, 'budynek'));
privacy_check(!str_contains($xml, '<?xml-stylesheet') && str_contains($xml, '/budynki/'), 'building URLs remain in unstyled XML');
privacy_check((bool) simplexml_load_string($xml), 'building sitemap remains valid XML');

wp_set_current_user(0);
foreach (array('/wp/v2/users', '/wp/v2/users/1', '/wp/v2/users/999999999', '/wp/v2/users/') as $route) {
    $response = rest_do_request(new WP_REST_Request('GET', $route));
    privacy_check(401 === $response->get_status(), 'anonymous user route denied consistently');
}
foreach (array('pl', 'en') as $language) {
    $request = new WP_REST_Request('GET', '/wp/v2/budynek');
    $request->set_param('language', $language);
    $request->set_param('per_page', 1);
    $response = rest_do_request($request);
    privacy_check(200 === $response->get_status() && count($response->get_data()) > 0, "$language map API retained");
}
$existing = new WP_Error('existing', 'Existing error', array('status' => 403));
privacy_check($existing === osada_core_protect_public_user_directory($existing, rest_get_server(), new WP_REST_Request('GET', '/wp/v2/users')), 'preserve earlier REST result');
privacy_check(null === osada_core_protect_public_user_directory(null, rest_get_server(), new WP_REST_Request('POST', '/wp/v2/users')), 'writes use normal WordPress permissions');
privacy_check(null === osada_core_protect_public_user_directory(null, rest_get_server(), new WP_REST_Request('GET', '/wp/v2/users/me')), 'current-user route uses normal authentication');

$admins = get_users(array('role' => 'administrator', 'number' => 1));
privacy_check((bool) $admins, 'administrator fixture exists');
$admin = $admins[0];
wp_set_current_user($admin->ID);
$request = new WP_REST_Request('GET', '/wp/v2/users');
$request->set_param('context', 'edit');
$request->set_param('per_page', 1);
privacy_check(200 === rest_do_request($request)->get_status(), 'authenticated administration keeps user access');
privacy_check(200 === rest_do_request(new WP_REST_Request('GET', '/wp/v2/users/me'))->get_status(), 'current-user endpoint retained');
$original_caps = wp_get_current_user()->allcaps;
wp_get_current_user()->allcaps['list_users'] = false;
privacy_check(403 === rest_do_request($request)->get_status(), 'authentication alone does not grant user administration');
wp_get_current_user()->allcaps = $original_caps;
wp_set_current_user(0);

foreach (array(array('author' => $admin->ID), array('author_name' => $admin->user_nicename)) as $args) {
    $GLOBALS['wp_query'] = new WP_Query($args);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    privacy_check(is_author(), 'author query fixture');
    add_action('template_redirect', 'redirect_canonical');
    osada_core_disable_author_routes();
    privacy_check(is_404() && !is_author(), 'author query becomes 404');
    privacy_check(0 === $GLOBALS['wp_query']->post_count && null === $GLOBALS['wp_query']->queried_object, 'no author object or posts rendered');
    privacy_check(false === has_action('template_redirect', 'redirect_canonical'), 'author slug redirect disabled');
}
$GLOBALS['wp_query'] = new WP_Query(array('post_type' => 'budynek', 'posts_per_page' => 1));
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$before = $GLOBALS['wp_query']->post_count;
osada_core_disable_author_routes();
privacy_check(!is_404() && $before === $GLOBALS['wp_query']->post_count, 'building archive unaffected');
$data = get_oembed_response_data($GLOBALS['wp_query']->posts[0]->ID, 600);
privacy_check(!isset($data['author_name'], $data['author_url']) && !empty($data['title']), 'oEmbed retains content without staff identity');
echo "$count account privacy and sitemap integration checks passed (read-only).\n";
