<?php
/**
 * Plugin Name: LL Simple Booking Appointments
 * Description: AJAX-based multi-step appointment booking with custom post type, admin table, and availability settings.
 * Version: 1.0.0
 * Author: Lievelingslinnen
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: ll-simple-booking
 */

if (! defined('ABSPATH')) {
    exit;
}

define('LLSBA_VERSION', '1.0.0');
define('LLSBA_FILE', __FILE__);
define('LLSBA_PATH', plugin_dir_path(__FILE__));
define('LLSBA_URL', plugin_dir_url(__FILE__));

require_once LLSBA_PATH . 'includes/class-plugin.php';

register_activation_hook(LLSBA_FILE, ['LLSBA\\Plugin', 'activate']);
register_deactivation_hook(LLSBA_FILE, ['LLSBA\\Plugin', 'deactivate']);

LLSBA\Plugin::instance();
