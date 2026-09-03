<?php
/**
 * ACF Local JSON configuration for field groups owned by Osada Core.
 *
 * @package Osada_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the directory containing the plugin's ACF field definitions.
 *
 * @return string
 */
function osada_core_get_acf_json_path() {
    return OSADA_CORE_PATH . 'acf-json';
}

/**
 * Make the plugin's field groups available to ACF.
 *
 * @param array $paths Existing ACF Local JSON load paths.
 * @return array
 */
function osada_core_add_acf_json_load_path($paths) {
    $path = osada_core_get_acf_json_path();

    if (!in_array($path, $paths, true)) {
        $paths[] = $path;
    }

    return $paths;
}
add_filter('acf/settings/load_json', 'osada_core_add_acf_json_load_path');

/**
 * Save Osada Core field groups back to the plugin when they are edited in ACF.
 *
 * The filter is registered only for known group keys, so unrelated ACF groups
 * continue to use their existing save location.
 *
 * @param string $path Existing save path.
 * @return string
 */
function osada_core_acf_json_save_path($path) {
    return osada_core_get_acf_json_path();
}
add_filter('acf/settings/save_json/key=group_699b618fb16dc', 'osada_core_acf_json_save_path');
add_filter('acf/settings/save_json/key=group_6a57dfcba78ed', 'osada_core_acf_json_save_path');
