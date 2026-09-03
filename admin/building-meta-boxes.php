<?php
/**
 * Building fields displayed in the WordPress administration area.
 *
 * @package Osada_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add marker settings to the building editor.
 */
function osada_core_add_building_marker_meta_box() {
    add_meta_box(
        'osada-building-marker-size',
        __('Rozmiar ikony na mapie', 'osada-core'),
        'osada_core_render_building_marker_meta_box',
        'budynek',
        'side',
        'default'
    );
}
add_action('add_meta_boxes_budynek', 'osada_core_add_building_marker_meta_box');

/**
 * Render marker width controls.
 *
 * @param WP_Post $post Current building.
 */
function osada_core_render_building_marker_meta_box($post) {
    wp_nonce_field('osada_building_marker_size', 'osada_building_marker_size_nonce');
    $width = absint(get_post_meta($post->ID, 'marker_icon_width', true));
    ?>
    <p>
        <label for="osada_marker_icon_width"><?php esc_html_e('Szerokość ikony (px)', 'osada-core'); ?></label>
        <input
            id="osada_marker_icon_width"
            name="osada_marker_icon_width"
            class="small-text"
            type="number"
            min="24"
            max="300"
            step="1"
            value="<?php echo $width ? esc_attr($width) : ''; ?>"
            placeholder="auto"
        >
    </p>
    <p class="description"><?php esc_html_e('Pozostaw puste, aby użyć automatycznego rozmiaru. Wysokość zostanie dopasowana proporcjonalnie.', 'osada-core'); ?></p>
    <?php
}

/**
 * Save marker width from the building editor.
 *
 * @param int $post_id Building ID.
 */
function osada_core_save_building_marker_width($post_id) {
    if (!isset($_POST['osada_building_marker_size_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['osada_building_marker_size_nonce']));
    if (!wp_verify_nonce($nonce, 'osada_building_marker_size')) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $width = isset($_POST['osada_marker_icon_width']) ? absint($_POST['osada_marker_icon_width']) : 0;

    if ($width >= 24 && $width <= 300) {
        update_post_meta($post_id, 'marker_icon_width', $width);
        return;
    }

    delete_post_meta($post_id, 'marker_icon_width');
}
add_action('save_post_budynek', 'osada_core_save_building_marker_width');
