<?php
/**
 * Language and translation controls in the WordPress editor.
 *
 * @package Osada_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add language settings to pages and buildings.
 */
function osada_core_add_language_meta_boxes() {
    foreach (array('page', 'budynek') as $post_type) {
        add_meta_box(
            'osada-language-settings',
            __('Language settings', 'osada-core'),
            'osada_core_render_language_meta_box',
            $post_type,
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'osada_core_add_language_meta_boxes');

/**
 * Render language and paired translation controls.
 *
 * @param WP_Post $post Current page or building.
 */
function osada_core_render_language_meta_box($post) {
    wp_nonce_field('osada_language_settings', 'osada_language_settings_nonce');

    $language       = get_post_meta($post->ID, '_osada_language', true) ?: 'pl';
    $translation_id = (int) get_post_meta($post->ID, '_osada_translation_id', true);
    $posts           = get_posts(array(
        'post_type'      => $post->post_type,
        'post_status'    => array('publish', 'draft', 'pending', 'private'),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'exclude'        => array($post->ID),
    ));
    ?>
    <p>
        <label for="osada_language"><?php esc_html_e('Language', 'osada-core'); ?></label>
        <select id="osada_language" name="osada_language" class="widefat">
            <option value="pl" <?php selected($language, 'pl'); ?>><?php esc_html_e('Polish', 'osada-core'); ?></option>
            <option value="en" <?php selected($language, 'en'); ?>><?php esc_html_e('English', 'osada-core'); ?></option>
        </select>
    </p>
    <p>
        <label for="osada_translation_id"><?php esc_html_e('Paired translation', 'osada-core'); ?></label>
        <select id="osada_translation_id" name="osada_translation_id" class="widefat">
            <option value="0"><?php esc_html_e('None', 'osada-core'); ?></option>
            <?php foreach ($posts as $translation_post) : ?>
                <?php if (!current_user_can('edit_post', $translation_post->ID)) continue; ?>
                <option value="<?php echo esc_attr($translation_post->ID); ?>" <?php selected($translation_id, $translation_post->ID); ?>>
                    <?php echo esc_html(get_the_title($translation_post)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}

/**
 * Save language and paired translation metadata.
 *
 * @param int $post_id Current post ID.
 */
function osada_core_save_language_meta($post_id) {
    if (!isset($_POST['osada_language_settings_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['osada_language_settings_nonce']));
    if (!wp_verify_nonce($nonce, 'osada_language_settings')) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)
        || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $post_type = get_post_type($post_id);
    if (!in_array($post_type, array('page', 'budynek'), true)) {
        return;
    }

    $language       = isset($_POST['osada_language']) ? sanitize_key(wp_unslash($_POST['osada_language'])) : 'pl';
    $language       = in_array($language, array('pl', 'en'), true) ? $language : 'pl';
    $translation_id = isset($_POST['osada_translation_id']) ? absint($_POST['osada_translation_id']) : 0;

    // Validate the complete change before modifying either side of a relationship.
    if ($translation_id && ($translation_id === (int) $post_id
        || get_post_type($translation_id) !== $post_type
        || !current_user_can('edit_post', $translation_id)
        || 'trash' === get_post_status($translation_id)
        || $language === (get_post_meta($translation_id, '_osada_language', true) ?: 'pl'))) {
        osada_core_language_pair_error();
        return;
    }

    $old_translation_id = (int) get_post_meta($post_id, '_osada_translation_id', true);
    $target_previous_id = $translation_id ? (int) get_post_meta($translation_id, '_osada_translation_id', true) : 0;
    $detach = array();
    if ($old_translation_id && $old_translation_id !== $translation_id
        && (int) get_post_meta($old_translation_id, '_osada_translation_id', true) === (int) $post_id) {
        $detach[] = $old_translation_id;
    }
    if ($target_previous_id && $target_previous_id !== (int) $post_id
        && (int) get_post_meta($target_previous_id, '_osada_translation_id', true) === $translation_id) {
        $detach[] = $target_previous_id;
    }
    foreach (array_unique($detach) as $detached_id) {
        if (!current_user_can('edit_post', $detached_id)) {
            osada_core_language_pair_error();
            return;
        }
    }

    foreach (array_unique($detach) as $detached_id) {
        delete_post_meta($detached_id, '_osada_translation_id');
    }
    update_post_meta($post_id, '_osada_language', $language);

    if ($translation_id) {
        update_post_meta($post_id, '_osada_translation_id', $translation_id);
        update_post_meta($translation_id, '_osada_translation_id', $post_id);
        return;
    }

    delete_post_meta($post_id, '_osada_translation_id');
}
add_action('save_post', 'osada_core_save_language_meta');

function osada_core_language_pair_error() {
    add_filter('redirect_post_location', function ($location) {
        return add_query_arg('osada_language_error', '1', $location);
    });
}

function osada_core_language_pair_notice() {
    if (!isset($_GET['osada_language_error']) || '1' !== $_GET['osada_language_error']) return;
    echo '<div class="notice notice-error"><p>';
    esc_html_e('Nie zapisano ustawień języka. Wybierz inny wpis tego samego typu w drugim języku. Musisz mieć prawo edycji obu wpisów i ich dotychczasowych tłumaczeń. Aby rozłączyć parę, wybierz „None”.', 'osada-core');
    echo '</p></div>';
}
add_action('admin_notices', 'osada_core_language_pair_notice');
