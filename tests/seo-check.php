<?php
/** Read-only integration checks against the local WordPress database. Run in CLI. */
if (PHP_SAPI !== 'cli') {
    exit;
}
$_SERVER['HTTP_HOST'] = 'osadafabryczna-produkcja.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname(__DIR__, 4) . '/wp-load.php';

$checks = 0;
function seo_check($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}
function seo_query($args) {
    $GLOBALS['wp_query'] = new WP_Query($args);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    $GLOBALS['post'] = $GLOBALS['wp_query']->post;
}
function seo_head() {
    ob_start();
    osada_core_seo_head();
    return ob_get_clean();
}
function seo_json($head) {
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $head, $match);
    return json_decode($match[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
}

seo_check(function_exists('osada_core_seo_head'), 'SEO module loaded');
seo_check(str_contains(osada_core_llms_content(), 'Citation guidance'), 'bundled citation guide available');
seo_check(str_contains(osada_core_llms_content(), home_url('/wp-sitemap.xml')), 'source guide uses actual site URL');
seo_check(!str_contains(osada_core_llms_content(), 'https://osadafabryczna.pl/'), 'source guide has no production links on local site');
seo_check(osada_core_seo_text('<script>alert(1)</script><p>Opis&nbsp; strony</p>') === 'Opis strony', 'HTML and scripts removed');
seo_check(osada_core_seo_text('<p>Pierwszy akapit</p><p>Drugi<br>wiersz</p>') === 'Pierwszy akapit Drugi wiersz', 'paragraph and line break boundaries');
seo_check(mb_strlen(osada_core_seo_text(str_repeat('Żyrardów ', 50), 160)) <= 160, 'Unicode truncation');

// Simulate publication only in this CLI process; do not save any settings.
add_filter('pre_option_osadafabryczna_access_enabled', '__return_zero');
$front_id = (int) get_option('page_on_front');
seo_check($front_id > 0, 'configured static front page');
seo_query(array('page_id' => $front_id));
$head = seo_head();
seo_check(str_contains($head, '<meta name="description"'), 'Polish front description');
seo_check(substr_count($head, 'rel="canonical"') === 1, 'single canonical');
$json = seo_json($head);
seo_check($json['@graph'][0]['@type'] === 'WebSite', 'website node');
seo_check(end($json['@graph'])['inLanguage'] === 'pl', 'Polish page schema');
seo_check(str_contains($head, 'hreflang="en"'), 'English front alternate');

$english = get_page_by_path('eng');
seo_check((bool) $english, 'English front exists');
seo_query(array('page_id' => $english->ID));
$head = seo_head();
seo_check(str_contains($head, 'Explore the historic factory settlement'), 'English front description');
seo_check(str_contains(get_language_attributes(), 'lang="en-GB"'), 'English HTML language');
seo_check(str_contains($head, 'og:locale" content="en_GB'), 'English social locale');

foreach (array('pl', 'en') as $language) {
    $args = array('post_type' => 'budynek', 'posts_per_page' => 2);
    if ('en' === $language) $args['osada_language_archive'] = 'en';
    seo_query($args);
    $head = seo_head();
    $json = seo_json($head);
    $page_node = end($json['@graph']);
    seo_check($page_node['@type'] === 'CollectionPage', "$language collection schema");
    seo_check($page_node['inLanguage'] === $language, "$language archive language");
    seo_check(str_contains(wp_get_document_title(), 'en' === $language ? 'Historic buildings' : 'Zabytki'), "$language localized archive title");
    seo_check(count($page_node['mainEntity']['itemListElement']) > 0, "$language archive links");
    seo_check(str_contains(osada_core_seo_canonical(), 'en' === $language ? '/eng/budynki/' : '/budynki/'), "$language archive canonical");
}
seo_query(array('post_type' => 'budynek', 'posts_per_page' => 2, 'paged' => 2, 'osada_language_archive' => 'en'));
seo_check(str_ends_with(osada_core_seo_canonical(), '/eng/budynki/page/2/'), 'paginated English canonical');
seo_check(array() === osada_core_seo_alternates(), 'no mismatched archive page alternates');

$buildings = get_posts(array('post_type' => 'budynek', 'numberposts' => -1, 'post_status' => 'publish'));
seo_check(count($buildings) > 0, 'building fixtures exist');
$languages = array();
foreach ($buildings as $building) {
    if ('' !== $building->post_password) continue;
    seo_query(array('post_type' => 'budynek', 'p' => $building->ID));
    $head = seo_head();
    $json = seo_json($head);
    $place = $json['@graph'][1];
    seo_check($place['@type'] === 'LandmarksOrHistoricalBuildings', 'building place schema');
    seo_check($place['name'] === osada_core_seo_text(get_the_title($building)), 'building title matches');
    seo_check('' !== $place['description'], 'nonempty building description');
    seo_check(end($json['@graph'])['mainEntity']['@id'] === $place['@id'], 'page references building');
    $lat = get_field('latitude', $building->ID);
    $lng = get_field('longitude', $building->ID);
    $valid_geo = is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180;
    seo_check(isset($place['geo']) === $valid_geo, 'coordinates only when valid');
    if ($valid_geo) {
        seo_check($place['geo']['latitude'] == (float) $lat && $place['geo']['longitude'] == (float) $lng, 'coordinates match building data');
    }
    $sources = get_field('sources', $building->ID);
    if ($sources) {
        seo_check(!empty(end($json['@graph'])['citation']), 'visible bibliography represented in schema');
    }
    $languages[osada_core_get_current_language()] = true;
}
seo_check(isset($languages['pl'], $languages['en']), 'tested Polish and English buildings');

// Exercise adversarial input without storing it in the database.
$id = get_queried_object_id();
$override = static function ($value, $object_id, $key) use ($id) {
    if ($object_id === $id && '_osada_meta_description' === $key) {
        return '</script><script>alert("x")</script>Opis "cytat" & źródło';
    }
    return $value;
};
add_filter('get_post_metadata', $override, 10, 3);
$head = seo_head();
seo_check(!str_contains($head, '<script>alert'), 'description cannot inject executable markup');
seo_check(str_contains($head, '&quot;cytat&quot;'), 'attribute escaping');
seo_check((bool) seo_json($head), 'JSON safely encoded');
remove_filter('get_post_metadata', $override, 10);

// Password protection is tested on a cloned in-memory post object.
$original_post = clone get_post($id);
$private_post = clone $original_post;
$private_post->post_password = 'integration-test';
wp_cache_set($id, $private_post, 'posts');
seo_check('' === seo_head(), 'password-protected metadata withheld');
seo_check(!empty(osada_core_seo_robots(array())['noindex']), 'password-protected noindex');
wp_cache_set($id, $original_post, 'posts');

remove_filter('pre_option_osadafabryczna_access_enabled', '__return_zero');
add_filter('pre_option_osadafabryczna_access_enabled', static function () { return '1'; });
add_filter('pre_option_osadafabryczna_access_password', static function () { return 'test-hash'; });
seo_check('' === seo_head(), 'prelaunch gate withholds metadata even for testers');
$robots = osada_core_seo_robots(array('index' => true, 'follow' => true));
seo_check(!empty($robots['noindex']) && !empty($robots['nofollow']) && !isset($robots['index'], $robots['follow']), 'gate indexing policy');
remove_all_filters('pre_option_osadafabryczna_access_enabled');
add_filter('pre_option_osadafabryczna_access_enabled', '__return_zero');
seo_query(array('s' => 'fabryka'));
seo_check('' === seo_head() && !empty(osada_core_seo_robots(array())['noindex']), 'internal search noindex');
$GLOBALS['wp_query']->set_404();
seo_check('' === seo_head() && !empty(osada_core_seo_robots(array())['noindex']), '404 noindex');
seo_query(array('page_id' => $front_id, 'preview' => true));
seo_check('' === seo_head(), 'preview metadata withheld');

// Verify editor saves using real capabilities/nonces; intercept every metadata write.
$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
seo_check((bool) $admins, 'administrator fixture exists');
wp_set_current_user((int) $admins[0]);
$mutations = array();
$intercept = static function ($value, $object_id, $key, $meta_value) use (&$mutations) {
    if ('_osada_meta_description' === $key) {
        $mutations[] = array($object_id, $meta_value);
    }
    return true;
};
add_filter('update_post_metadata', $intercept, 10, 4);
add_filter('add_post_metadata', $intercept, 10, 4);
add_filter('delete_post_metadata', $intercept, 10, 4);
$_POST = array('osada_seo_nonce' => wp_create_nonce('osada_seo_description'), 'osada_meta_description' => 'Opis <b>zabytku</b>');
osada_core_save_seo_description($front_id);
seo_check(count($mutations) === 1 && $mutations[0] === array($front_id, 'Opis zabytku'), 'authorized save sanitizes description');
$mutations = array();
$_POST['osada_meta_description'] = '';
osada_core_save_seo_description($front_id);
seo_check(count($mutations) === 1, 'clearing override restores automatic description');
$mutations = array();
$_POST['osada_seo_nonce'] = 'invalid';
osada_core_save_seo_description($front_id);
seo_check(array() === $mutations, 'invalid nonce cannot save');
wp_set_current_user(0);
$_POST['osada_seo_nonce'] = wp_create_nonce('osada_seo_description');
osada_core_save_seo_description($front_id);
seo_check(array() === $mutations, 'unauthorized user cannot save');
$_POST = array();
remove_filter('update_post_metadata', $intercept, 10);
remove_filter('add_post_metadata', $intercept, 10);
remove_filter('delete_post_metadata', $intercept, 10);

seo_query(array('page_id' => $front_id));
add_action('wp_head', 'rel_canonical');
ob_start();
do_action('wp_head');
$full_head = ob_get_clean();
seo_check(substr_count($full_head, 'rel="canonical"') === 1, 'rendered WordPress head has exactly one canonical');
seo_check(substr_count($full_head, 'name="description"') === 1, 'rendered WordPress head has exactly one description');
seo_check(substr_count($full_head, 'type="application/ld+json"') === 1, 'rendered WordPress head has exactly one schema graph');
seo_check((bool) seo_json($full_head), 'rendered WordPress JSON parses');
define('WPSEO_VERSION', 'integration-test');
seo_check('' === seo_head(), 'dedicated SEO plugin takes over without duplicate metadata');

echo "$checks SEO integration checks passed (read-only).\n";
