<?php declare(strict_types=1);

namespace Gratis\Community\CLI;

defined('ABSPATH') || exit;

/**
 * WP-CLI commands for Gratis Community.
 */
final class CommunityCommand {

    /**
     * Show the latest activity.
     *
     * @when after_wp_load
     */
    public function activity($args, $assoc_args): void {
        unset($args, $assoc_args);
        $activities = get_posts(['post_type' => 'gratis_activity', 'numberposts' => 20]);
        if (empty($activities)) {
            \WP_CLI::log('No activity.');
            return;
        }
        foreach ($activities as $a) {
            $name = get_the_author_meta('display_name', $a->post_author);
            $time = human_time_diff((int) strtotime($a->post_date));
            \WP_CLI::log("  {$name} {$a->post_content} ({$time} ago)");
        }
    }

    /**
     * List groups.
     *
     * @when after_wp_load
     */
    public function groups($args, $assoc_args): void {
        unset($args, $assoc_args);
        $groups = get_posts(['post_type' => 'gratis_group', 'numberposts' => -1]);
        if (empty($groups)) {
            \WP_CLI::log('No groups.');
            return;
        }
        $rows = array_map(static fn($g) => ['ID' => $g->ID, 'Name' => $g->post_title], $groups);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Name']);
    }

    /**
     * Send a message.
     *
     * <to_user_id>
     * : Recipient user ID.
     *
     * <message>
     * : Message text.
     *
     * @when after_wp_load
     */
    public function message($args, $assoc_args): void {
        unset($assoc_args);
        $to = (int) $args[0];
        $id = wp_insert_post([
            'post_type'    => 'gratis_message',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id() ?: 1,
            'post_content' => sanitize_textarea_field((string) $args[1]),
            'meta_input'   => ['_recipient' => $to],
        ]);
        if (is_wp_error($id)) {
            \WP_CLI::error('Failed to send message.');
        }
        \WP_CLI::success("Message sent to user #{$to}");
    }
}
