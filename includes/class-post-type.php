<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class Post_Type
{
    public const TYPE = 'llsba_booking';

    public function __construct()
    {
        add_action('init', [self::class, 'register']);
    }

    public static function register(): void
    {
        register_post_type(self::TYPE, [
            'labels' => [
                'name'          => __('Bookings', 'll-simple-booking'),
                'singular_name' => __('Booking', 'll-simple-booking'),
                'menu_name'     => __('Bookings', 'll-simple-booking'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'supports'            => ['title'],
            'has_archive'         => false,
            'rewrite'             => false,
            'menu_icon'           => 'dashicons-calendar-alt',
            'capability_type'     => 'post',
            'exclude_from_search' => true,
        ]);
    }
}
