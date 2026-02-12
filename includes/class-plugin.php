<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class Plugin
{
    /** @var Plugin|null */
    private static $instance = null;

    /** @var Post_Type */
    private $post_type;

    /** @var Settings */
    private $settings;

    /** @var Shortcode */
    private $shortcode;

    /** @var Ajax */
    private $ajax;

    /** @var Admin */
    private $admin;

    public static function instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        Post_Type::register();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    private function __construct()
    {
        $this->load_dependencies();

        $this->post_type = new Post_Type();
        $this->settings  = new Settings();
        $this->shortcode = new Shortcode($this->settings);
        $this->ajax      = new Ajax($this->settings);
        $this->admin     = new Admin($this->settings);
    }

    private function load_dependencies(): void
    {
        require_once LLSBA_PATH . 'includes/class-post-type.php';
        require_once LLSBA_PATH . 'includes/class-settings.php';
        require_once LLSBA_PATH . 'includes/class-shortcode.php';
        require_once LLSBA_PATH . 'includes/class-ajax.php';
        require_once LLSBA_PATH . 'includes/class-admin.php';
    }
}
