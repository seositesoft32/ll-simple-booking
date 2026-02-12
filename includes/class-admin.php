<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class Admin
{
    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this->settings, 'register']);
    }

    public function menu(): void
    {
        add_menu_page(
            __('Simple Booking', 'll-simple-booking'),
            __('Simple Booking', 'll-simple-booking'),
            'manage_options',
            'llsba-bookings',
            [$this, 'bookings_page'],
            'dashicons-calendar-alt',
            57
        );

        add_submenu_page(
            'llsba-bookings',
            __('Bookings', 'll-simple-booking'),
            __('Bookings', 'll-simple-booking'),
            'manage_options',
            'llsba-bookings',
            [$this, 'bookings_page']
        );

        add_submenu_page(
            'llsba-bookings',
            __('Settings', 'll-simple-booking'),
            __('Settings', 'll-simple-booking'),
            'manage_options',
            'llsba-settings',
            [$this, 'settings_page']
        );
    }

    public function bookings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $query = new \WP_Query([
            'post_type'      => Post_Type::TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bookings', 'll-simple-booking'); ?></h1>
            <table class="widefat striped fixed">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'll-simple-booking'); ?></th>
                        <th><?php esc_html_e('Contact Number', 'll-simple-booking'); ?></th>
                        <th><?php esc_html_e('Date', 'll-simple-booking'); ?></th>
                        <th><?php esc_html_e('Time', 'll-simple-booking'); ?></th>
                        <th><?php esc_html_e('Created', 'll-simple-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($query->have_posts()) : ?>
                        <?php while ($query->have_posts()) : $query->the_post(); ?>
                            <tr>
                                <td><?php echo esc_html((string) get_the_ID()); ?></td>
                                <td><?php echo esc_html((string) get_post_meta(get_the_ID(), '_llsba_contact', true)); ?></td>
                                <td><?php echo esc_html((string) get_post_meta(get_the_ID(), '_llsba_date', true)); ?></td>
                                <td><?php echo esc_html((string) get_post_meta(get_the_ID(), '_llsba_time', true)); ?></td>
                                <td><?php echo esc_html((string) get_the_date('Y-m-d H:i')); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php wp_reset_postdata(); ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No bookings found.', 'll-simple-booking'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings->get();
        $days     = [
            'monday'    => __('Monday', 'll-simple-booking'),
            'tuesday'   => __('Tuesday', 'll-simple-booking'),
            'wednesday' => __('Wednesday', 'll-simple-booking'),
            'thursday'  => __('Thursday', 'll-simple-booking'),
            'friday'    => __('Friday', 'll-simple-booking'),
            'saturday'  => __('Saturday', 'll-simple-booking'),
            'sunday'    => __('Sunday', 'll-simple-booking'),
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Booking Settings', 'll-simple-booking'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('llsba_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="llsba-slot-duration"><?php esc_html_e('Slot duration (minutes)', 'll-simple-booking'); ?></label></th>
                        <td><input id="llsba-slot-duration" type="number" min="5" name="<?php echo esc_attr($this->settings->option_key()); ?>[slot_duration]" value="<?php echo esc_attr((string) $settings['slot_duration']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="llsba-start-time"><?php esc_html_e('Start hour', 'll-simple-booking'); ?></label></th>
                        <td><input id="llsba-start-time" type="time" name="<?php echo esc_attr($this->settings->option_key()); ?>[start_time]" value="<?php echo esc_attr((string) $settings['start_time']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="llsba-end-time"><?php esc_html_e('End hour', 'll-simple-booking'); ?></label></th>
                        <td><input id="llsba-end-time" type="time" name="<?php echo esc_attr($this->settings->option_key()); ?>[end_time]" value="<?php echo esc_attr((string) $settings['end_time']); ?>" /></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Availability by day', 'll-simple-booking'); ?></h2>
                <table class="widefat striped" style="max-width: 820px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Day', 'll-simple-booking'); ?></th>
                            <th><?php esc_html_e('Enable', 'll-simple-booking'); ?></th>
                            <th><?php esc_html_e('Max bookings per day', 'll-simple-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $key => $label) : ?>
                            <tr>
                                <td><?php echo esc_html($label); ?></td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr($this->settings->option_key()); ?>[days][<?php echo esc_attr($key); ?>]" value="1" <?php checked((int) $settings['days'][$key], 1); ?> />
                                        <?php esc_html_e('Available', 'll-simple-booking'); ?>
                                    </label>
                                </td>
                                <td>
                                    <input type="number" min="0" name="<?php echo esc_attr($this->settings->option_key()); ?>[day_limits][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $settings['day_limits'][$key]); ?>" />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'll-simple-booking')); ?>
            </form>
        </div>
        <?php
    }
}
