<?php
/** This guide has no public author directory; keep editorial accounts private. */
if (!defined('ABSPATH')) {
    exit;
}

/** Removing the provider also makes direct users-sitemap requests return 404. */
function osada_core_remove_users_sitemap($provider, $name) {
    return 'users' === $name ? false : $provider;
}
add_filter('wp_sitemaps_add_provider', 'osada_core_remove_users_sitemap', 10, 2);

// XML sitemaps do not need browser-side XSLT, which browsers are retiring.
add_filter('wp_sitemaps_stylesheet_url', '__return_empty_string');
add_filter('wp_sitemaps_stylesheet_index_url', '__return_empty_string');

/** Preserve authenticated editor access and all normal REST permission checks. */
function osada_core_protect_public_user_directory($result, $server, $request) {
    if (null !== $result || is_user_logged_in()
        || !in_array($request->get_method(), array('GET', 'HEAD'), true)
        || !preg_match('~^/wp/v2/users(?:/[0-9]+)?/?$~', $request->get_route())) {
        return $result;
    }
    return new WP_Error('osada_user_directory_private', __('Authentication required.', 'osada-core'), array('status' => 401));
}
add_filter('rest_pre_dispatch', 'osada_core_protect_public_user_directory', 10, 3);

/** Stop author URLs and author sitemaps before canonical redirects can expose names. */
function osada_core_disable_author_routes() {
    if (!is_author() && 'users' !== get_query_var('sitemap')) {
        return;
    }
    global $wp_query, $post;
    remove_action('template_redirect', 'redirect_canonical');
    $wp_query->set_404();
    $wp_query->posts = array();
    $wp_query->post = null;
    $wp_query->post_count = 0;
    $wp_query->found_posts = 0;
    $wp_query->max_num_pages = 0;
    $wp_query->queried_object = null;
    $wp_query->queried_object_id = 0;
    $wp_query->set('author', '');
    $wp_query->set('author_name', '');
    $post = null;
    status_header(404);
    nocache_headers();
}
add_action('template_redirect', 'osada_core_disable_author_routes', -5);

/** oEmbed's optional author fields should not expose a staff account either. */
function osada_core_remove_oembed_author($data) {
    unset($data['author_name'], $data['author_url']);
    return $data;
}
add_filter('oembed_response_data', 'osada_core_remove_oembed_author');
