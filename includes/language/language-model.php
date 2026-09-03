<?php
/**
 * Language state and translation relationships.
 *
 * @package Osada_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return whether the English landing page is being displayed.
 *
 * @return bool
 */
function osada_core_is_english_front_page() {
    return is_page('eng');
}

/**
 * Return whether the English building archive is being displayed.
 *
 * @return bool
 */
function osada_core_is_english_budynek_archive() {
    return is_post_type_archive('budynek') && 'en' === get_query_var('osada_language_archive');
}

/**
 * Get the language assigned to a post.
 *
 * Posts without explicit language metadata remain Polish for backwards
 * compatibility with content created before the language fields existed.
 *
 * @param int|null $post_id Post ID. Defaults to the queried object.
 * @return string
 */
function osada_core_get_post_language($post_id = null) {
    $post_id  = $post_id ?: get_queried_object_id();
    $language = $post_id ? get_post_meta($post_id, '_osada_language', true) : '';

    if ('en' === $language) {
        return 'en';
    }

    if (osada_core_is_english_front_page()) {
        return 'en';
    }

    return 'pl';
}

/**
 * Resolve the language of the current public request.
 *
 * @return string
 */
function osada_core_get_current_language() {
    if (is_search() && 'en' === get_query_var('osada_search_language')) {
        return 'en';
    }

    if (is_singular()) {
        return osada_core_get_post_language();
    }

    if (osada_core_is_english_budynek_archive()) {
        return 'en';
    }

    return osada_core_is_english_front_page() ? 'en' : 'pl';
}

/**
 * Get the post paired as the current post's translation.
 *
 * @param int|null $post_id Post ID. Defaults to the queried object.
 * @return int
 */
function osada_core_get_paired_translation_id($post_id = null) {
    $post_id = $post_id ?: get_queried_object_id();

    return $post_id ? (int) get_post_meta($post_id, '_osada_translation_id', true) : 0;
}

/**
 * Get the English map landing page URL.
 *
 * @return string
 */
function osada_core_get_english_front_page_url() {
    $english_page = get_page_by_path('eng');

    return $english_page ? get_permalink($english_page) : home_url('/eng/');
}

/**
 * Get the matching URL for a language switch.
 *
 * @param string $language Target language code.
 * @return string
 */
function osada_core_get_language_url($language) {
    if (is_search()) {
        $search_args = array('s' => get_search_query(false));

        if ('en' === $language) {
            $search_args['osada_search_language'] = 'en';
        }

        return add_query_arg($search_args, home_url('/'));
    }

    if (is_singular()) {
        $post_id         = get_queried_object_id();
        $current_language = osada_core_get_post_language($post_id);

        if ($language === $current_language) {
            return get_permalink($post_id);
        }

        $translation_id = osada_core_get_paired_translation_id($post_id);
        if ($translation_id) {
            return get_permalink($translation_id);
        }
    }

    if (is_post_type_archive('budynek')) {
        if ('en' === $language) {
            return home_url('/eng/budynki/');
        }

        return get_post_type_archive_link('budynek');
    }

    if ('en' === $language) {
        return osada_core_get_english_front_page_url();
    }

    return home_url('/');
}
