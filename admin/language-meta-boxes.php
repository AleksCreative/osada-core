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

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $post_type = get_post_type($post_id);
    if (!in_array($post_type, array('page', 'budynek'), true)) {
        return;
    }

    $language       = isset($_POST['osada_language']) ? sanitize_key(wp_unslash($_POST['osada_language'])) : 'pl';
    $language       = in_array($language, array('pl', 'en'), true) ? $language : 'pl';
    $translation_id = isset($_POST['osada_translation_id']) ? absint($_POST['osada_translation_id']) : 0;

    update_post_meta($post_id, '_osada_language', $language);

    if ($translation_id) {
        update_post_meta($post_id, '_osada_translation_id', $translation_id);
        update_post_meta($translation_id, '_osada_translation_id', $post_id);
        return;
    }

    delete_post_meta($post_id, '_osada_translation_id');
}
add_action('save_post', 'osada_core_save_language_meta');
