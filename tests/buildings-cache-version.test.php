<?php
// CLI-only integration of registered hooks with isolated metadata/options.
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH', '/');
$hooks = array();
$posts = array(10 => 'budynek', 20 => 'page', 30 => 'revision');
$version = 1;
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook][] = array($callback, $args); }
function get_post_type($id) { return $GLOBALS['posts'][$id] ?? false; }
function wp_is_post_revision($id) { return 30 === $id; }
function wp_is_post_autosave($id) { return 40 === $id; }
function get_option($key, $default = false) { return $GLOBALS['version']; }
function update_option($key, $value, $autoload = null) { $GLOBALS['version'] = $value; }
function fire_hook($hook, ...$args) {
    foreach ($GLOBALS['hooks'][$hook] ?? array() as [$callback, $accepted]) $callback(...array_slice($args, 0, $accepted));
}
require dirname(__DIR__) . '/includes/building-model.php';
$checks = 0;
function check_version($expected, $label) {
    if ($GLOBALS['version'] !== $expected) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $GLOBALS['checks']++;
}
fire_hook('wp_after_insert_post', 10, (object) array('post_type' => 'budynek'));
check_version(2, 'final post save invalidates cache');
foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $hook) {
    foreach (array('latitude', 'longitude', 'marker_icon', 'marker_icon_width', 'subtitle', 'short_description', '_osada_language') as $field) {
        $expected = $version + 1;
        fire_hook($hook, 1, 10, $field, 'test');
        check_version($expected, "$hook $field");
    }
}
$unchanged = $version;
fire_hook('updated_post_meta', 1, 10, '_edit_lock', 'test');
fire_hook('updated_post_meta', 1, 20, 'subtitle', 'test');
fire_hook('wp_after_insert_post', 20, (object) array('post_type' => 'page'));
fire_hook('wp_after_insert_post', 30, (object) array('post_type' => 'revision'));
fire_hook('wp_after_insert_post', 40, (object) array('post_type' => 'budynek'));
check_version($unchanged, 'unrelated metadata, pages, revisions and autosaves ignored');
unset($posts[10]);
fire_hook('deleted_post', 10, (object) array('post_type' => 'budynek'));
check_version($unchanged + 1, 'permanent deletion invalidates even after post is removed');
fire_hook('deleted_post', 20, (object) array('post_type' => 'page'));
check_version($unchanged + 1, 'unrelated deletion ignored');
echo "$checks building cache invalidation checks passed.\n";
