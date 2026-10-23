<?php
/**
 * Building content type, public data helpers and REST representation.
 *
 * @package Osada_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the building content type.
 */
function osada_register_budynek_cpt() {
    register_post_type('budynek', array(
        'labels' => array(
            'name'          => __('Budynki', 'osada-core'),
            'singular_name' => __('Budynek', 'osada-core'),
            'add_new'       => __('Dodaj budynek', 'osada-core'),
            'add_new_item'  => __('Dodaj nowy budynek', 'osada-core'),
            'edit_item'     => __('Edytuj budynek', 'osada-core'),
            'new_item'      => __('Nowy budynek', 'osada-core'),
            'view_item'     => __('Zobacz budynek', 'osada-core'),
            'search_items'  => __('Szukaj budynków', 'osada-core'),
        ),
        'public'            => true,
        'has_archive'       => true,
        'show_in_nav_menus' => true,
        'rewrite'           => array('slug' => 'budynki'),
        'menu_icon'         => 'dashicons-location',
        'supports'          => array('title', 'editor', 'excerpt', 'thumbnail'),
        'show_in_rest'      => true,
    ));
}
add_action('init', 'osada_register_budynek_cpt');

/**
 * Return the custom marker URL assigned to a building.
 *
 * A theme-specific fallback is intentionally not supplied here. The plugin
 * owns the building data, while the active theme owns its presentation assets.
 *
 * @param int $post_id Building ID. Defaults to the current post.
 * @return string
 */
function osada_core_get_building_marker_url($post_id = 0) {
    $post_id         = $post_id ? absint($post_id) : get_the_ID();
    $marker_icon     = function_exists('get_field') ? get_field('marker_icon', $post_id) : '';
    $marker_icon_url = '';

    if (is_array($marker_icon)) {
        if (!empty($marker_icon['url'])) {
            $marker_icon_url = $marker_icon['url'];
        } elseif (!empty($marker_icon['ID'])) {
            $marker_icon_url = wp_get_attachment_image_url(absint($marker_icon['ID']), 'full');
        }
    } elseif (is_numeric($marker_icon)) {
        $marker_icon_url = wp_get_attachment_image_url(absint($marker_icon), 'full');
    } elseif (is_string($marker_icon)) {
        $marker_icon_url = $marker_icon;
    }

    return $marker_icon_url ?: '';
}

/**
 * Register building metadata used by the map.
 */
function osada_core_register_building_meta() {
    register_post_meta('budynek', 'marker_icon_width', array(
        'type'              => 'integer',
        'single'            => true,
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'show_in_rest'      => array(
            'schema' => array(
                'type'    => 'integer',
                'default' => 0,
            ),
        ),
        'auth_callback'     => '__return_true',
    ));
}
add_action('init', 'osada_core_register_building_meta');

/**
 * Read marker width for the top-level REST field used by the map.
 *
 * @param array $post_data Prepared REST post data.
 * @return int
 */
function osada_core_get_building_marker_width_rest($post_data) {
    return absint(get_post_meta($post_data['id'], 'marker_icon_width', true));
}

/**
 * Keep the existing top-level REST field for backwards compatibility.
 */
function osada_core_register_building_rest_fields() {
    register_rest_field('budynek', 'marker_icon_width', array(
        'get_callback' => 'osada_core_get_building_marker_width_rest',
        'schema'       => array(
            'description' => __('Szerokość ikony budynku na mapie w pikselach.', 'osada-core'),
            'type'        => 'integer',
            'context'     => array('view', 'edit'),
            'readonly'    => true,
        ),
    ));
}
add_action('rest_api_init', 'osada_core_register_building_rest_fields');

/**
 * Change the map data version after the final save or permanent deletion.
 *
 * @param int $post_id Building ID.
 */
function osada_core_bump_buildings_cache_version($post_id, $post = null) {
    $post_type = isset($post->post_type) ? $post->post_type : get_post_type($post_id);
    if ('budynek' !== $post_type || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $version = (int) get_option('osadafabryczna_buildings_cache_version', 1);
    update_option('osadafabryczna_buildings_cache_version', $version + 1, false);
}
// wp_after_insert_post also runs after REST updates have finished saving metadata.
add_action('wp_after_insert_post', 'osada_core_bump_buildings_cache_version', 20, 2);
add_action('deleted_post', 'osada_core_bump_buildings_cache_version', 20, 2);

/** Field updates can also happen without saving the post (ACF, REST, imports). */
function osada_core_invalidate_building_meta_cache($meta_id, $post_id, $meta_key) {
    if (in_array($meta_key, array(
        'latitude', 'longitude', 'marker_icon', 'marker_icon_width',
        'subtitle', 'short_description', '_osada_language',
    ), true)) {
        osada_core_bump_buildings_cache_version($post_id);
    }
}
foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $osada_meta_hook) {
    add_action($osada_meta_hook, 'osada_core_invalidate_building_meta_cache', 20, 3);
}
