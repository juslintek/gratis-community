<?php declare(strict_types=1);

namespace Gratis\Community;

defined('ABSPATH') || exit;

/**
 * Editable community profile fields on the WordPress user screen.
 */
final class ProfileFields {

    private const NONCE = 'gratis_community_profile';

    /** Render the bio / avatar / social fields. */
    public static function render(\WP_User $user): void {
        wp_nonce_field(self::NONCE, self::NONCE);
        $bio    = (string) get_user_meta($user->ID, '_gratis_bio', true);
        $avatar = (string) get_user_meta($user->ID, '_gratis_avatar_url', true);
        $social = (string) get_user_meta($user->ID, '_gratis_social', true);

        echo '<h2>' . esc_html__('Community Profile', 'gratis-community') . '</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th><label for="gc_bio">' . esc_html__('Bio', 'gratis-community') . '</label></th><td>';
        echo '<textarea id="gc_bio" name="gc_bio" rows="4" class="regular-text">' . esc_textarea($bio) . '</textarea>';
        echo '</td></tr>';

        echo '<tr><th><label for="gc_avatar">' . esc_html__('Avatar URL', 'gratis-community') . '</label></th><td>';
        echo '<input type="url" id="gc_avatar" name="gc_avatar" class="regular-text" value="' . esc_attr($avatar) . '">';
        echo '</td></tr>';

        echo '<tr><th><label for="gc_social">' . esc_html__('Social URL', 'gratis-community') . '</label></th><td>';
        echo '<input type="url" id="gc_social" name="gc_social" class="regular-text" value="' . esc_attr($social) . '">';
        echo '</td></tr>';

        echo '</tbody></table>';
    }

    /** Persist the profile fields (nonce + capability gated). */
    public static function save(int $user_id): void {
        if (! isset($_POST[self::NONCE]) || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), self::NONCE)) {
            return;
        }
        if (! current_user_can('edit_user', $user_id)) {
            return;
        }

        // Nonce + capability verified above.
        update_user_meta($user_id, '_gratis_bio', sanitize_textarea_field(wp_unslash($_POST['gc_bio'] ?? '')));
        update_user_meta($user_id, '_gratis_avatar_url', esc_url_raw(wp_unslash($_POST['gc_avatar'] ?? '')));
        update_user_meta($user_id, '_gratis_social', esc_url_raw(wp_unslash($_POST['gc_social'] ?? '')));
    }
}
