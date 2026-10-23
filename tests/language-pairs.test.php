<?php
// Isolated CLI tests of metadata changes; no WordPress database writes.
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH', '/');
function add_action(...$args) {}
function add_filter(...$args) { $GLOBALS['rejected'] = true; }
function sanitize_text_field($value) { return $value; }
function sanitize_key($value) { return $value; }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int) $value); }
function wp_verify_nonce(...$args) { return $GLOBALS['nonce_valid']; }
function wp_is_post_revision($id) { return false; }
function current_user_can($cap, $id) { return !in_array($id, $GLOBALS['denied'], true); }
function get_post_type($id) { return $GLOBALS['posts'][$id]['type'] ?? false; }
function get_post_status($id) { return $GLOBALS['posts'][$id]['status'] ?? false; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); }
require dirname(__DIR__) . '/admin/language-meta-boxes.php';

function reset_pairs() {
    $GLOBALS['posts'] = array_fill_keys(array(10, 20, 30, 40), array('type' => 'budynek', 'status' => 'publish'));
    $GLOBALS['meta'] = array(
        10 => array('_osada_language' => 'pl', '_osada_translation_id' => 20),
        20 => array('_osada_language' => 'en', '_osada_translation_id' => 10),
        30 => array('_osada_language' => 'en', '_osada_translation_id' => 40),
        40 => array('_osada_language' => 'pl', '_osada_translation_id' => 30),
    );
    $GLOBALS['denied'] = array();
    $GLOBALS['rejected'] = false;
    $GLOBALS['nonce_valid'] = true;
    $_POST = array('osada_language_settings_nonce' => 'test', 'osada_language' => 'pl', 'osada_translation_id' => 30);
}
function expect_pair($condition, $label) {
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $GLOBALS['checks']++;
}
$checks = 0;
reset_pairs();
osada_core_save_language_meta(10);
expect_pair(30 === get_post_meta(10, '_osada_translation_id', true) && 10 === get_post_meta(30, '_osada_translation_id', true), 'new reciprocal pair');
expect_pair('' === get_post_meta(20, '_osada_translation_id', true) && '' === get_post_meta(40, '_osada_translation_id', true), 'both displaced partners detached');
$_POST['osada_translation_id'] = 0;
osada_core_save_language_meta(10);
expect_pair('' === get_post_meta(10, '_osada_translation_id', true) && '' === get_post_meta(30, '_osada_translation_id', true), 'None detaches both sides');

foreach (array('self', 'missing', 'type', 'language', 'trash', 'target permission', 'old permission', 'displaced permission') as $case) {
    reset_pairs();
    if ('self' === $case) $_POST['osada_translation_id'] = 10;
    if ('missing' === $case) $_POST['osada_translation_id'] = 99;
    if ('type' === $case) $posts[30]['type'] = 'page';
    if ('language' === $case) $meta[30]['_osada_language'] = 'pl';
    if ('trash' === $case) $posts[30]['status'] = 'trash';
    if ('target permission' === $case) $denied = array(30);
    if ('old permission' === $case) $denied = array(20);
    if ('displaced permission' === $case) $denied = array(40);
    $before = $meta;
    osada_core_save_language_meta(10);
    expect_pair($meta === $before && $rejected, "reject without partial writes: $case");
}
reset_pairs();
$meta[20]['_osada_translation_id'] = 40;
osada_core_save_language_meta(10);
expect_pair(40 === get_post_meta(20, '_osada_translation_id', true), 'do not remove unrelated nonreciprocal link');
reset_pairs();
$nonce_valid = false;
$before = $meta;
osada_core_save_language_meta(10);
expect_pair($meta === $before, 'invalid nonce cannot write');
echo "$checks language pair checks passed.\n";
