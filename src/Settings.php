<?php declare(strict_types=1);

namespace Gratis\Community;

defined('ABSPATH') || exit;

/**
 * Admin settings for Gratis Community.
 */
final class Settings {

    private const GROUP = 'gratis_community_settings_group';
    private const PAGE  = 'gratis-community-settings';

    private const OPT_LOGGING = 'gratis_community_logging';
    private const OPT_FEED     = 'gratis_community_feed_count';

    /** Register the settings submenu under the Activity menu. */
    public static function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=gratis_activity',
            __('Community Settings', 'gratis-community'),
            __('Settings', 'gratis-community'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /** Register options with typed sanitisers. */
    public static function register_settings(): void {
        register_setting(self::GROUP, self::OPT_LOGGING, [
            'type'              => 'boolean',
            'sanitize_callback' => [__CLASS__, 'sanitize_bool'],
            'default'           => true,
        ]);
        register_setting(self::GROUP, self::OPT_FEED, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 10,
        ]);
    }

    /** Normalise a checkbox value. */
    public static function sanitize_bool($value): bool {
        return (bool) $value;
    }

    /** Whether activity logging is enabled. */
    public static function loggingEnabled(): bool {
        return (bool) get_option(self::OPT_LOGGING, true);
    }

    /** Number of items in the activity feed shortcode. */
    public static function feedCount(): int {
        return max(1, (int) get_option(self::OPT_FEED, 10));
    }

    /** Render the settings screen. */
    public static function render_page(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-community'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Community Settings', 'gratis-community') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">' . esc_html__('Activity logging', 'gratis-community') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_LOGGING) . '" value="1"' . checked(self::loggingEnabled(), true, false) . '> ' . esc_html__('Record posts and form submissions in the activity feed', 'gratis-community') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="gc_feed">' . esc_html__('Feed items', 'gratis-community') . '</label></th><td>';
        echo '<input type="number" min="1" id="gc_feed" class="small-text" name="' . esc_attr(self::OPT_FEED) . '" value="' . esc_attr((string) self::feedCount()) . '">';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save settings', 'gratis-community'));
        echo '</form>';

        echo '<h2>' . esc_html__('Shortcodes', 'gratis-community') . '</h2>';
        echo '<p><code>[gratis-profile user="ID"]</code> &mdash; ' . esc_html__('profile card', 'gratis-community') . '</p>';
        echo '<p><code>[gratis-activity-feed]</code> &mdash; ' . esc_html__('latest activity', 'gratis-community') . '</p>';
        echo '</div>';
    }
}
