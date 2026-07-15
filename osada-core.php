<?php
/**
 * Plugin Name: Osada Core
 * Description: Custom Post Types for Osada Fabryczna
 */

if (!defined('ABSPATH')) exit;

// Register CPT: Budynek
function osada_register_budynek_cpt() {

    register_post_type('budynek', [
        'labels' => [
            'name' => 'Budynki',
            'singular_name' => 'Budynek',
            'add_new' => 'Dodaj budynek',
            'add_new_item' => 'Dodaj nowy budynek',
            'edit_item' => 'Edytuj budynek',
            'new_item' => 'Nowy budynek',
            'view_item' => 'Zobacz budynek',
            'search_items' => 'Szukaj budynków'
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_nav_menus' => true,
        'rewrite' => ['slug' => 'budynki'],
        'menu_icon' => 'dashicons-location',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
        'show_in_rest' => true
    ]);
}

add_action('init', 'osada_register_budynek_cpt');
