<?php
/** Optional description overrides, stored separately for each PL/EN page. */
if (!defined('ABSPATH')) {
    exit;
}

function osada_core_add_seo_meta_box() {
    if (!osada_core_seo_enabled()) {
        return;
    }
    foreach (array('page', 'budynek') as $type) {
        add_meta_box('osada-seo', 'SEO — opis w wyszukiwarce', 'osada_core_render_seo_meta_box', $type, 'normal');
    }
}
add_action('add_meta_boxes', 'osada_core_add_seo_meta_box');

function osada_core_render_seo_meta_box($post) {
    wp_nonce_field('osada_seo_description', 'osada_seo_nonce');
    ?>
    <p><label for="osada-meta-description">Meta description</label></p>
    <textarea id="osada-meta-description" name="osada_meta_description" rows="3" class="widefat"><?php echo esc_textarea(get_post_meta($post->ID, '_osada_meta_description', true)); ?></textarea>
    <p class="description">Krótko opisz zawartość tej strony. Zalecana długość: około 140–160 znaków. Pozostaw puste, aby opis powstał automatycznie z treści. Każda wersja językowa ma osobny opis. Wyszukiwarka może wyświetlić własny fragment tekstu.</p>
    <?php
}

function osada_core_save_seo_description($post_id) {
    if (!in_array(get_post_type($post_id), array('page', 'budynek'), true)
        || !isset($_POST['osada_seo_nonce'], $_POST['osada_meta_description'])
        || !is_string($_POST['osada_seo_nonce']) || !is_string($_POST['osada_meta_description'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['osada_seo_nonce'])), 'osada_seo_description')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $description = sanitize_text_field(wp_unslash($_POST['osada_meta_description']));
    if ('' === $description) {
        delete_post_meta($post_id, '_osada_meta_description');
    } else {
        update_post_meta($post_id, '_osada_meta_description', $description);
    }
}
add_action('save_post', 'osada_core_save_seo_description');
