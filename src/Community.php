<?php declare(strict_types=1);

namespace Gratis\Community;

defined('ABSPATH') || exit;

/**
 * Community engine: activity feed, groups, messages, profiles and REST.
 */
final class Community {

    /** Register CPTs, user meta, shortcodes and activity hooks. */
    public static function register(): void {
        register_post_type('gratis_activity', [
            'labels'       => ['name' => __('Activity', 'gratis-community')],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-buddicons-activity',
        ]);

        register_post_type('gratis_group', [
            'labels'       => ['name' => __('Groups', 'gratis-community'), 'singular_name' => __('Group', 'gratis-community')],
            'public'       => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-groups',
            'supports'     => ['title', 'editor', 'thumbnail'],
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'groups'],
        ]);

        register_post_type('gratis_message', [
            'labels'       => ['name' => __('Messages', 'gratis-community')],
            'public'       => false,
            'show_ui'      => false,
            'show_in_rest' => true,
        ]);

        register_meta('user', '_gratis_bio', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_textarea_field']);
        register_meta('user', '_gratis_avatar_url', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'esc_url_raw']);
        register_meta('user', '_gratis_social', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'esc_url_raw']);

        add_shortcode('gratis-profile', [__CLASS__, 'profileShortcode']);
        add_shortcode('gratis-activity-feed', [__CLASS__, 'feedShortcode']);

        if (Settings::loggingEnabled()) {
            add_action('publish_post', [__CLASS__, 'onPublishPost']);
            add_action('gratis_form_submitted', [__CLASS__, 'onFormSubmitted']);
        }
    }

    /** Activity hook: a post was published. */
    public static function onPublishPost(int $postId): void {
        self::logActivity(get_current_user_id(), __('published a post', 'gratis-community'), $postId);
    }

    /** Activity hook: a Gratis form was submitted. */
    public static function onFormSubmitted(): void {
        self::logActivity(get_current_user_id(), __('submitted a form', 'gratis-community'));
    }

    /** Register the community REST routes. */
    public static function registerAPI(): void {
        register_rest_route('gratis-community/v1', '/activity', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => function () {
                $activities = get_posts(['post_type' => 'gratis_activity', 'numberposts' => 20]);
                return new \WP_REST_Response(array_map(static fn($a) => [
                    'id'      => $a->ID,
                    'content' => $a->post_content,
                    'user'    => get_the_author_meta('display_name', $a->post_author),
                    'date'    => $a->post_date,
                ], $activities));
            },
        ]);

        register_rest_route('gratis-community/v1', '/messages', [
            'methods'             => 'POST',
            'permission_callback' => 'is_user_logged_in',
            'callback'            => function (\WP_REST_Request $req) {
                $data = $req->get_json_params();
                $id   = wp_insert_post([
                    'post_type'    => 'gratis_message',
                    'post_status'  => 'publish',
                    'post_author'  => get_current_user_id(),
                    'post_content' => sanitize_textarea_field((string) ($data['message'] ?? '')),
                    'meta_input'   => ['_recipient' => (int) ($data['to'] ?? 0)],
                ]);
                return new \WP_REST_Response(['sent' => ! is_wp_error($id), 'id' => $id]);
            },
        ]);
    }

    /** Record an activity-feed entry. */
    public static function logActivity(int $userId, string $action, int $objectId = 0): void {
        if (! $userId) {
            return;
        }
        wp_insert_post([
            'post_type'    => 'gratis_activity',
            'post_status'  => 'publish',
            'post_author'  => $userId,
            'post_content' => $action,
            'meta_input'   => ['_object_id' => $objectId],
        ]);
    }

    /** [gratis-profile] — render a user profile card. */
    public static function profileShortcode($atts): string {
        $atts    = shortcode_atts(['user' => 0], $atts, 'gratis-profile');
        $user_id = (int) $atts['user'] ?: get_current_user_id();
        $user    = get_userdata($user_id);
        if (! $user) {
            return '<p>' . esc_html__('User not found.', 'gratis-community') . '</p>';
        }

        $bio    = (string) get_user_meta($user_id, '_gratis_bio', true);
        $since  = gmdate('F Y', (int) strtotime($user->user_registered));
        $html   = '<div class="gratis-profile">';
        $html  .= '<h2>' . esc_html($user->display_name) . '</h2>';
        if ($bio !== '') {
            $html .= '<p>' . esc_html($bio) . '</p>';
        }
        $html  .= '<p>' . esc_html__('Member since:', 'gratis-community') . ' ' . esc_html($since) . '</p>';
        $html  .= '</div>';
        return $html;
    }

    /** [gratis-activity-feed] — render the latest activity. */
    public static function feedShortcode(): string {
        $activities = get_posts(['post_type' => 'gratis_activity', 'numberposts' => Settings::feedCount()]);
        if (empty($activities)) {
            return '<p>' . esc_html__('No activity yet.', 'gratis-community') . '</p>';
        }
        $html = '<div class="gratis-activity-feed">';
        foreach ($activities as $a) {
            $name  = get_the_author_meta('display_name', $a->post_author);
            $ago   = human_time_diff((int) strtotime($a->post_date));
            $html .= '<div class="gratis-activity-item" style="padding:12px 0;border-bottom:1px solid rgba(0,0,0,0.06)">';
            $html .= '<strong>' . esc_html($name) . '</strong> ' . esc_html($a->post_content);
            $html .= ' <small style="color:#94a3b8">' . esc_html(sprintf(/* translators: %s: human time diff. */ __('%s ago', 'gratis-community'), $ago)) . '</small>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}
