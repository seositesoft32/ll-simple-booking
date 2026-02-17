<?php
/**
 * Plugin Name: LL Simple Booking Appointments
 * Description: AJAX-based multi-step appointment booking with custom post type, admin table, and availability settings.
 * Version: 1.5
 * Author: Lievelingslinnen
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 10.0
 * Update URI: lievelingslinnen.com/ll-simple-booking
 * Text Domain: ll-simple-booking
 */

if (! defined('ABSPATH')) {
    exit;
}

define('LLSBA_VERSION', '1.5'); 
define('LLSBA_FILE', __FILE__);
define('LLSBA_PATH', plugin_dir_path(__FILE__));
define('LLSBA_URL', plugin_dir_url(__FILE__));

require_once LLSBA_PATH . 'includes/class-plugin.php';

add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', LLSBA_FILE, true);
    }
});

register_activation_hook(LLSBA_FILE, ['LLSBA\\Plugin', 'activate']);
register_deactivation_hook(LLSBA_FILE, ['LLSBA\\Plugin', 'deactivate']);

LLSBA\Plugin::instance();
