<?php
/**
 * Plugin Name: Osada Core
 * Description: Model danych i funkcje aplikacyjne serwisu Osada Fabryczna.
 * Version: 1.2.0
 * Text Domain: osada-core
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OSADA_CORE_VERSION', '1.2.0');
define('OSADA_CORE_PATH', plugin_dir_path(__FILE__));

require_once OSADA_CORE_PATH . 'includes/building-model.php';
require_once OSADA_CORE_PATH . 'includes/acf-json.php';
require_once OSADA_CORE_PATH . 'admin/building-meta-boxes.php';

/**
 * Register plugin rewrite rules before flushing them on activation.
 */
function osada_core_activate() {
    osada_register_budynek_cpt();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'osada_core_activate');

/**
 * Remove stale rewrite rules after deactivation.
 */
function osada_core_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'osada_core_deactivate');
