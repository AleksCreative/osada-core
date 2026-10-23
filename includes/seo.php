<?php
/** Public metadata derived from the same content that visitors can read. */
if (!defined('ABSPATH')) {
    exit;
}

/** Defer metadata to a dedicated SEO plugin if one is installed later. */
function osada_core_seo_enabled() {
    return !defined('WPSEO_VERSION') && !defined('RANK_MATH_VERSION')
        && !defined('AIOSEO_VERSION') && !defined('SEOPRESS_VERSION')
        && !defined('THE_SEO_FRAMEWORK_VERSION');
}

function osada_core_seo_is_private() {
    return (function_exists('osadafabryczna_access_is_enabled') && osadafabryczna_access_is_enabled())
        || (is_singular() && (!is_post_publicly_viewable(get_queried_object_id())
            || '' !== (string) get_post_field('post_password', get_queried_object_id())));
}

/** Plain text without shortcodes, HTML, script content or repeated whitespace. */
function osada_core_seo_text($value, $limit = 0) {
    if (!is_scalar($value)) {
        return '';
    }
    $text = strip_shortcodes((string) $value);
    $text = preg_replace('~<(?:br\b[^>]*|/(?:p|div|h[1-6]|li|blockquote))>~i', ' ', $text);
    // Remove markup before decoding: encoded literal tags must stay literal text.
    $text = html_entity_decode(wp_strip_all_tags($text, true), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($limit && mb_strlen($text, 'UTF-8') > $limit) {
        $prefix = mb_substr($text, 0, $limit - 1, 'UTF-8');
        $boundary = mb_strrpos($prefix, ' ', 0, 'UTF-8');
        if (false !== $boundary && $boundary > $limit / 2) {
            $prefix = mb_substr($prefix, 0, $boundary, 'UTF-8');
        }
        $text = rtrim($prefix, " ,;:-") . '…';
    }
    return $text;
}

function osada_core_seo_description($post_id = 0) {
    $english = 'en' === osada_core_get_current_language();
    if ($post_id) {
        $custom = osada_core_seo_text(get_post_meta($post_id, '_osada_meta_description', true));
        if ('' !== $custom) {
            return $custom;
        }
    }
    if (is_front_page() || osada_core_is_english_front_page()) {
        return $english
            ? 'Explore the historic factory settlement in Żyrardów. An interactive map, building histories, architecture and archival photographs in one guide.'
            : 'Odkryj Osadę Fabryczną w Żyrardowie. Interaktywna mapa zabytków, historie budynków, architektura i archiwalne fotografie w jednym przewodniku.';
    }
    if (is_post_type_archive('budynek')) {
        return $english
            ? 'Discover the historic buildings of the factory settlement in Żyrardów. Explore their history, architecture and location on the interactive map.'
            : 'Poznaj zabytki Osady Fabrycznej w Żyrardowie. Odkryj historię i architekturę budynków oraz ich położenie na interaktywnej mapie.';
    }
    if (!$post_id) {
        return '';
    }
    $candidates = array(get_post_field('post_excerpt', $post_id));
    if ('budynek' === get_post_type($post_id) && function_exists('get_field')) {
        $candidates[] = get_field('short_description', $post_id);
        $candidates[] = get_field('history', $post_id);
    }
    $candidates[] = get_post_field('post_content', $post_id);
    foreach ($candidates as $candidate) {
        $text = osada_core_seo_text($candidate, 160);
        if ('' !== $text) {
            return $text;
        }
    }
    return osada_core_seo_text(get_the_title($post_id), 160);
}

/** Canonicals contain no tracking parameters; pagination keeps its own URL. */
function osada_core_seo_canonical() {
    if (is_singular()) {
        return wp_get_canonical_url(get_queried_object_id());
    }
    if (is_front_page()) {
        return home_url('/');
    }
    if (is_post_type_archive('budynek')) {
        $url = osada_core_is_english_budynek_archive()
            ? home_url('/eng/budynki/') : get_post_type_archive_link('budynek');
        $page = max(1, (int) get_query_var('paged'));
        if ($page > 1) {
            if (get_option('permalink_structure')) {
                global $wp_rewrite;
                $url = trailingslashit($url) . user_trailingslashit($wp_rewrite->pagination_base . '/' . $page, 'paged');
            } else {
                $url = add_query_arg('paged', $page, $url);
            }
        }
        return $url;
    }
    return '';
}

/** Only actual, public, reciprocal translations qualify as hreflang alternates. */
function osada_core_seo_alternates() {
    $urls = array();
    if (is_front_page() || osada_core_is_english_front_page()) {
        $urls['pl'] = home_url('/');
        $english = get_page_by_path('eng');
        if ($english && is_post_publicly_viewable($english) && '' === $english->post_password) {
            $urls['en'] = get_permalink($english);
        }
        return $urls;
    }
    if (is_post_type_archive('budynek')) {
        // On subsequent archive pages the language collections may have different lengths.
        if ((int) get_query_var('paged') < 2) {
            $urls = array('pl' => get_post_type_archive_link('budynek'), 'en' => home_url('/eng/budynki/'));
        }
        return $urls;
    }
    if (!is_singular() || (int) get_query_var('page') > 1) {
        return $urls;
    }
    $id = get_queried_object_id();
    $language = osada_core_get_current_language();
    $urls[$language] = get_permalink($id);
    $pair = get_post(osada_core_get_paired_translation_id($id));
    if ($pair && $pair->ID !== $id && $pair->post_type === get_post_type($id)
        && is_post_publicly_viewable($pair) && '' === $pair->post_password
        && $id === osada_core_get_paired_translation_id($pair->ID)) {
        $pair_language = 'en' === get_post_meta($pair->ID, '_osada_language', true) ? 'en' : 'pl';
        if ($pair_language !== $language) {
            $urls[$pair_language] = get_permalink($pair);
        }
    }
    return $urls;
}

function osada_core_seo_image($post_id) {
    $image = get_the_post_thumbnail_url($post_id, 'full');
    if (!$image && 'budynek' === get_post_type($post_id) && function_exists('get_field')) {
        $hero = get_field('hero_illustration', $post_id);
        if (is_array($hero)) {
            $image = !empty($hero['ID']) ? wp_get_attachment_image_url($hero['ID'], 'full') : ($hero['url'] ?? '');
        } elseif (is_numeric($hero)) {
            $image = wp_get_attachment_image_url((int) $hero, 'full');
        } elseif (is_string($hero)) {
            $image = $hero;
        }
    }
    return $image ? esc_url_raw($image, array('http', 'https')) : '';
}

/** A building is a place, not a business, review, product or museum endorsement. */
function osada_core_seo_building($id, $url, $description, $image) {
    $place = array(
        '@type' => 'LandmarksOrHistoricalBuildings',
        '@id' => $url . '#building',
        'url' => $url,
        'name' => osada_core_seo_text(get_the_title($id)),
        'description' => $description,
        'mainEntityOfPage' => array('@id' => $url . '#webpage'),
    );
    if ($image) {
        $place['image'] = $image;
    }
    if (!function_exists('get_field')) {
        return $place;
    }
    $lat = get_field('latitude', $id);
    $lng = get_field('longitude', $id);
    if (is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180) {
        $place['geo'] = array('@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng);
    }
    foreach ((array) get_field('quick_facts', $id) as $fact) {
        if (is_array($fact) && 'address' === ($fact['fact_type'] ?? '')) {
            $address = osada_core_seo_text($fact['fact_content'] ?? '');
            if ($address) {
                $place['address'] = $address;
            }
            break;
        }
    }
    return $place;
}

function osada_core_seo_graph($url, $description, $image) {
    $home = home_url('/');
    $id = is_singular() ? get_queried_object_id() : 0;
    $page = array(
        '@type' => is_post_type_archive('budynek') ? 'CollectionPage' : 'WebPage',
        '@id' => $url . '#webpage', 'url' => $url,
        'name' => osada_core_seo_text(wp_get_document_title()),
        'description' => $description,
        'inLanguage' => osada_core_get_current_language(),
        'isPartOf' => array('@id' => $home . '#website'),
    );
    if ($image) {
        $page['primaryImageOfPage'] = array('@type' => 'ImageObject', 'url' => $image);
    }
    if ($id) {
        $page['dateModified'] = get_post_modified_time(DATE_W3C, true, $id);
    }
    $graph = array(array(
        '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home,
        'name' => osada_core_seo_text(get_bloginfo('name')),
        'inLanguage' => array('pl', 'en'),
    ));
    if (is_singular('budynek')) {
        $page['mainEntity'] = array('@id' => $url . '#building');
        $graph[] = osada_core_seo_building($id, $url, $description, $image);
        $citations = array();
        $sources = function_exists('get_field') ? get_field('sources', $id) : array();
        foreach ((array) $sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $name = osada_core_seo_text($source['source_text'] ?? '');
            $source_url = esc_url_raw($source['source_url'] ?? '', array('http', 'https'));
            if ($name || $source_url) {
                $citation = array('@type' => 'CreativeWork');
                if ($name) $citation['name'] = $name;
                if ($source_url) $citation['url'] = $source_url;
                $citations[] = $citation;
            }
        }
        if ($citations) {
            $page['citation'] = $citations;
        }
    } elseif (is_post_type_archive('budynek')) {
        global $wp_query;
        $items = array();
        foreach ($wp_query->posts as $post) {
            if (!is_post_publicly_viewable($post) || '' !== $post->post_password) {
                continue;
            }
            $items[] = array('@type' => 'ListItem', 'position' => count($items) + 1,
                'name' => osada_core_seo_text(get_the_title($post)), 'url' => get_permalink($post));
        }
        $page['mainEntity'] = array('@type' => 'ItemList', 'itemListElement' => $items);
    }
    $graph[] = $page;
    return array('@context' => 'https://schema.org', '@graph' => $graph);
}

function osada_core_seo_head() {
    if (!osada_core_seo_enabled() || osada_core_seo_is_private() || is_search() || is_404() || is_preview()) {
        return;
    }
    $url = osada_core_seo_canonical();
    if (!$url) {
        return;
    }
    // WordPress normally supplies singular canonicals at priority 10.
    remove_action('wp_head', 'rel_canonical');
    $id = is_singular() ? get_queried_object_id() : 0;
    $description = osada_core_seo_description($id);
    $image = $id ? osada_core_seo_image($id) : '';
    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    foreach (osada_core_seo_alternates() as $language => $alternate) {
        echo '<link rel="alternate" hreflang="' . esc_attr($language) . '" href="' . esc_url($alternate) . '">' . "\n";
    }
    $social = array('og:type' => 'website', 'og:site_name' => get_bloginfo('name'),
        'og:title' => osada_core_seo_text(wp_get_document_title()), 'og:description' => $description,
        'og:url' => $url, 'og:locale' => 'en' === osada_core_get_current_language() ? 'en_GB' : 'pl_PL');
    if ($image) $social['og:image'] = $image;
    foreach ($social as $property => $value) {
        echo '<meta property="' . esc_attr($property) . '" content="' . esc_attr($value) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '">' . "\n";
    echo '<script type="application/ld+json">' . wp_json_encode(osada_core_seo_graph($url, $description, $image),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>' . "\n";
}
add_action('wp_head', 'osada_core_seo_head', 5);

/** Preserve explicit indexing opt-outs and protect test/private contexts. */
function osada_core_seo_robots($robots) {
    if (osada_core_seo_is_private() || is_preview() || is_search() || is_404()) {
        $robots['noindex'] = true;
        unset($robots['index']);
    }
    if (osada_core_seo_is_private()) {
        $robots['nofollow'] = true;
        unset($robots['follow']);
    }
    return $robots;
}
add_filter('wp_robots', 'osada_core_seo_robots');

/** The WordPress locale alone does not reflect this project's PL/EN routing. */
function osada_core_seo_language_attributes($attributes) {
    if (is_admin()) {
        return $attributes;
    }
    $language = 'en' === osada_core_get_current_language() ? 'en-GB' : 'pl-PL';
    return preg_replace('/\b((?:xml:)?lang)=("|\x27)[^"\x27]*\2/', '$1="' . $language . '"', $attributes);
}
add_filter('language_attributes', 'osada_core_seo_language_attributes');

function osada_core_seo_document_title_parts($parts) {
    if (osada_core_seo_enabled() && is_post_type_archive('budynek') && !osada_core_seo_is_private()) {
        $parts['title'] = 'en' === osada_core_get_current_language() ? 'Historic buildings' : 'Zabytki';
    }
    return $parts;
}
add_filter('document_title_parts', 'osada_core_seo_document_title_parts');
