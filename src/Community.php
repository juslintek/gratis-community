<?php declare(strict_types=1);
namespace Gratis\Community;

final class Community
{
    public static function register(): void
    {
        // Activity feed
        register_post_type('gratis_activity', [
            'labels' => ['name' => 'Activity'], 'public' => false, 'show_ui' => true,
            'show_in_rest' => true, 'menu_icon' => 'dashicons-buddicons-activity',
        ]);

        // Groups
        register_post_type('gratis_group', [
            'labels' => ['name' => 'Groups', 'singular_name' => 'Group'],
            'public' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-groups',
            'supports' => ['title', 'editor', 'thumbnail'], 'has_archive' => true,
            'rewrite' => ['slug' => 'groups'],
        ]);

        // Messages
        register_post_type('gratis_message', [
            'labels' => ['name' => 'Messages'], 'public' => false, 'show_ui' => false,
            'show_in_rest' => true,
        ]);

        // User profile fields
        register_meta('user', '_gratis_bio', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_meta('user', '_gratis_avatar_url', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_meta('user', '_gratis_social', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);

        // Profile page shortcode
        add_shortcode('gratis-profile', [__CLASS__, 'profileShortcode']);
        add_shortcode('gratis-activity-feed', [__CLASS__, 'feedShortcode']);

        // Log activity on key actions
        add_action('publish_post', fn($id) => self::logActivity(get_current_user_id(), 'published a post', $id));
        add_action('gratis_form_submitted', fn($fid, $data, $eid) => self::logActivity(get_current_user_id(), 'submitted a form'), 10, 3);
    }

    public static function registerAPI(): void
    {
        register_rest_route('gratis-community/v1', '/activity', [
            'methods' => 'GET', 'permission_callback' => '__return_true',
            'callback' => function () {
                $activities = get_posts(['post_type' => 'gratis_activity', 'numberposts' => 20]);
                return new \WP_REST_Response(array_map(fn($a) => [
                    'id' => $a->ID, 'content' => $a->post_content,
                    'user' => get_the_author_meta('display_name', $a->post_author),
                    'date' => $a->post_date,
                ], $activities));
            },
        ]);

        register_rest_route('gratis-community/v1', '/messages', [
            'methods' => 'POST', 'permission_callback' => 'is_user_logged_in',
            'callback' => function ($req) {
                $data = $req->get_json_params();
                $id = wp_insert_post([
                    'post_type' => 'gratis_message', 'post_status' => 'publish',
                    'post_author' => get_current_user_id(),
                    'post_content' => sanitize_textarea_field($data['message'] ?? ''),
                    'meta_input' => ['_recipient' => (int) ($data['to'] ?? 0)],
                ]);
                return new \WP_REST_Response(['sent' => true, 'id' => $id]);
            },
        ]);
    }

    public static function logActivity(int $userId, string $action, int $objectId = 0): void
    {
        if (!$userId) return;
        wp_insert_post([
            'post_type' => 'gratis_activity', 'post_status' => 'publish',
            'post_author' => $userId,
            'post_content' => $action,
            'meta_input' => ['_object_id' => $objectId],
        ]);
    }

    public static function profileShortcode($atts): string
    {
        $atts = shortcode_atts(['user' => 0], $atts);
        $user_id = (int) $atts['user'] ?: get_current_user_id();
        $user = get_userdata($user_id);
        if (!$user) return '<p>User not found.</p>';

        $bio = get_user_meta($user_id, '_gratis_bio', true);
        $html = '<div class="gratis-profile">';
        $html .= '<h2>' . esc_html($user->display_name) . '</h2>';
        if ($bio) $html .= '<p>' . esc_html($bio) . '</p>';
        $html .= '<p>Member since: ' . date('F Y', strtotime($user->user_registered)) . '</p>';
        $html .= '</div>';
        return $html;
    }

    public static function feedShortcode(): string
    {
        $activities = get_posts(['post_type' => 'gratis_activity', 'numberposts' => 10]);
        if (empty($activities)) return '<p>No activity yet.</p>';
        $html = '<div class="gratis-activity-feed">';
        foreach ($activities as $a) {
            $name = get_the_author_meta('display_name', $a->post_author);
            $html .= '<div class="gratis-activity-item" style="padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.06)">';
            $html .= '<strong>' . esc_html($name) . '</strong> ' . esc_html($a->post_content);
            $html .= ' <small style="color:rgba(255,255,255,0.4)">' . human_time_diff(strtotime($a->post_date)) . ' ago</small>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}
