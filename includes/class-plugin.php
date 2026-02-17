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

    /** @var License */
    private $license;

    /** @var Self_Hosted_Manager */
    private $self_hosted;

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
        (new License())->ensure_cron();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        License::clear_cron();
        flush_rewrite_rules();
    }

    private function __construct()
    {
        $this->load_dependencies();

        $this->post_type = new Post_Type();
        $this->settings  = new Settings();
        $this->license   = new License();
        $this->self_hosted = new Self_Hosted_Manager($this->license);
        $this->license->maybe_recheck();
        $this->shortcode = new Shortcode($this->settings, $this->license);
        $this->ajax      = new Ajax($this->settings, $this->license);
        $this->admin     = new Admin($this->settings, $this->license);
    }

    private function load_dependencies(): void
    {
        require_once LLSBA_PATH . 'includes/class-post-type.php';
        require_once LLSBA_PATH . 'includes/class-settings.php';
        require_once LLSBA_PATH . 'includes/class-ids.php';
        require_once LLSBA_PATH . 'includes/class-license.php';
        require_once LLSBA_PATH . 'includes/class-self-hosted-manager.php';
        require_once LLSBA_PATH . 'includes/class-shortcode.php';
        require_once LLSBA_PATH . 'includes/class-ajax.php';
        require_once LLSBA_PATH . 'includes/class-admin.php';
    }
}
