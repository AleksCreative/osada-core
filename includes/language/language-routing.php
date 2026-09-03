<?php
/**
 * Language-aware routes, queries and permalinks.
 *
 * @package Osada_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register English building routes.
 */
function osada_core_add_language_rewrites() {
    add_rewrite_rule('^eng/budynek/([^/]+)/?$', 'index.php?budynek=$matches[1]', 'top');
    add_rewrite_rule('^eng/budynki/page/([0-9]+)/?$', 'index.php?post_type=budynek&osada_language_archive=en&paged=$matches[1]', 'top');
    add_rewrite_rule('^eng/budynki/?$', 'index.php?post_type=budynek&osada_language_archive=en', 'top');
}
add_action('init', 'osada_core_add_language_rewrites');

/**
 * Register public language query variables.
 *
 * @param array $query_vars Existing public query variables.
 * @return array
 */
function osada_core_add_language_query_vars($query_vars) {
    $query_vars[] = 'osada_language_archive';
    $query_vars[] = 'osada_search_language';

    return $query_vars;
}
add_filter('query_vars', 'osada_core_add_language_query_vars');

/**
 * Restrict building archives to their requested language.
 *
 * @param WP_Query $query Current query.
 */
function osada_core_filter_budynek_archive_query($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    $is_english_archive = 'en' === $query->get('osada_language_archive');
    $is_polish_archive  = $query->is_post_type_archive('budynek');

    if (!$is_english_archive && !$is_polish_archive) {
        return;
    }

    $language = $is_english_archive ? 'en' : 'pl';
    $query->set('post_type', 'budynek');

    $meta_query = $query->get('meta_query');
    $meta_query = is_array($meta_query) ? $meta_query : array();

    if ('en' === $language) {
        $meta_query[] = array(
            'key'     => '_osada_language',
            'value'   => 'en',
            'compare' => '=',
        );
    } else {
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => '_osada_language',
                'value'   => 'pl',
                'compare' => '=',
            ),
            array(
                'key'     => '_osada_language',
                'compare' => 'NOT EXISTS',
            ),
        );
    }

    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'osada_core_filter_budynek_archive_query');

/**
 * Restrict public site search to pages and buildings in one language.
 *
 * @param WP_Query $query Current query.
 */
function osada_core_filter_search_query($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }

    $language = 'en' === $query->get('osada_search_language') ? 'en' : 'pl';
    $query->set('post_type', array('page', 'budynek'));

    $meta_query = $query->get('meta_query');
    $meta_query = is_array($meta_query) ? $meta_query : array();

    if ('en' === $language) {
        $meta_query[] = array(
            'key'     => '_osada_language',
            'value'   => 'en',
            'compare' => '=',
        );
    } else {
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => '_osada_language',
                'value'   => 'pl',
                'compare' => '=',
            ),
            array(
                'key'     => '_osada_language',
                'compare' => 'NOT EXISTS',
            ),
        );
    }

    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'osada_core_filter_search_query');

/**
 * Restrict the building REST collection to one language.
 *
 * @param array           $args    WP_Query arguments.
 * @param WP_REST_Request $request REST request.
 * @return array
 */
function osada_core_filter_budynek_rest_query($args, $request) {
    $language = sanitize_key($request->get_param('language'));

    if (!in_array($language, array('pl', 'en'), true)) {
        $language = 'pl';
    }

    $args['meta_query'] = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : array();
    $language_query     = array(
        'relation' => 'OR',
        array(
            'key'     => '_osada_language',
            'value'   => $language,
            'compare' => '=',
        ),
    );

    if ('pl' === $language) {
        $language_query[] = array(
            'key'     => '_osada_language',
            'compare' => 'NOT EXISTS',
        );
    }

    $args['meta_query'][] = $language_query;

    return $args;
}
add_filter('rest_budynek_query', 'osada_core_filter_budynek_rest_query', 10, 2);

/**
 * Use the English route for English building permalinks.
 *
 * @param string  $post_link Default permalink.
 * @param WP_Post $post      Building post.
 * @return string
 */
function osada_core_filter_english_budynek_link($post_link, $post) {
    if ('budynek' === $post->post_type && 'en' === osada_core_get_post_language($post->ID)) {
        return home_url('/eng/budynek/' . $post->post_name . '/');
    }

    return $post_link;
}
add_filter('post_type_link', 'osada_core_filter_english_budynek_link', 10, 2);

/**
 * Keep English archive pagination inside the English route.
 *
 * @param string $link        Default page URL.
 * @param int    $page_number Page number.
 * @return string
 */
function osada_core_filter_english_budynek_archive_pagination_link($link, $page_number) {
    if (!osada_core_is_english_budynek_archive()) {
        return $link;
    }

    if ($page_number > 1) {
        return home_url('/eng/budynki/page/' . $page_number . '/');
    }

    return home_url('/eng/budynki/');
}
add_filter('get_pagenum_link', 'osada_core_filter_english_budynek_archive_pagination_link', 10, 2);
