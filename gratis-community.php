<?php
/**
 * Plugin Name: Gratis Community
 * Plugin URI: https://github.com/juslintek/gratis-community
 * Description: Social features: user profiles, activity feed, messaging, and groups.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-community
 */

defined('ABSPATH') || exit;

define('GRATIS_COMMUNITY_VERSION', '1.0.0');
define('GRATIS_COMMUNITY_FILE', __FILE__);
define('GRATIS_COMMUNITY_DIR', plugin_dir_path(__FILE__));
define('GRATIS_COMMUNITY_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('init', [\Gratis\Community\Community::class, 'register']);
add_action('rest_api_init', [\Gratis\Community\Community::class, 'registerAPI']);

// Editable profile fields on the user screen.
add_action('show_user_profile', [\Gratis\Community\ProfileFields::class, 'render']);
add_action('edit_user_profile', [\Gratis\Community\ProfileFields::class, 'render']);
add_action('personal_options_update', [\Gratis\Community\ProfileFields::class, 'save']);
add_action('edit_user_profile_update', [\Gratis\Community\ProfileFields::class, 'save']);

// Admin settings page.
add_action('admin_menu', [\Gratis\Community\Settings::class, 'register_menu']);
add_action('admin_init', [\Gratis\Community\Settings::class, 'register_settings']);

add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-community', \Gratis\Community\CLI\CommunityCommand::class);
    }
});
