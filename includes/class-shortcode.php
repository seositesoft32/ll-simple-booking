<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class Shortcode
{
    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        add_shortcode('llsba_booking_form', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        wp_register_style(
            'llsba-frontend',
            LLSBA_URL . 'assets/css/frontend.css',
            [],
            LLSBA_VERSION
        );

        wp_register_script(
            'llsba-frontend',
            LLSBA_URL . 'assets/js/frontend.js',
            ['jquery'],
            LLSBA_VERSION,
            true
        );
    }

    public function render(): string
    {
        wp_enqueue_style('llsba-frontend');
        wp_enqueue_script('llsba-frontend');

        wp_localize_script('llsba-frontend', 'llsbaData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('llsba_nonce'),
            'today'   => wp_date('Y-m-d'),
            'labels'  => [
                'step1'          => __('Step 1: Contact Number', 'll-simple-booking'),
                'step2'          => __('Step 2: Calendar', 'll-simple-booking'),
                'step3'          => __('Step 3: Time Slots', 'll-simple-booking'),
                'step4'          => __('Step 4: Thank You', 'll-simple-booking'),
                'chooseDate'     => __('Please choose a date first.', 'll-simple-booking'),
                'chooseSlot'     => __('Please choose a time slot.', 'll-simple-booking'),
                'loading'        => __('Loading...', 'll-simple-booking'),
                'noSlots'        => __('No available slots for this date.', 'll-simple-booking'),
                'bookingSaved'   => __('Your appointment is booked. Thank you!', 'll-simple-booking'),
                'bookingsLabel'  => __('Bookings', 'll-simple-booking'),
                'availableLabel' => __('Available', 'll-simple-booking'),
            ],
        ]);

        ob_start();
        ?>
        <div class="llsba-wrap" id="llsba-booking-form">
            <div class="llsba-steps">
                <span class="is-active" data-step-label="1"><?php esc_html_e('1 Contact', 'll-simple-booking'); ?></span>
                <span data-step-label="2"><?php esc_html_e('2 Calendar', 'll-simple-booking'); ?></span>
                <span data-step-label="3"><?php esc_html_e('3 Time Slots', 'll-simple-booking'); ?></span>
                <span data-step-label="4"><?php esc_html_e('4 Thank You', 'll-simple-booking'); ?></span>
            </div>

            <div class="llsba-step is-active" data-step="1">
                <h3><?php esc_html_e('Contact number', 'll-simple-booking'); ?></h3>
                <input type="text" id="llsba-contact" placeholder="+31 ..." />
                <button type="button" class="button button-primary" data-next-step="2"><?php esc_html_e('Next', 'll-simple-booking'); ?></button>
            </div>

            <div class="llsba-step" data-step="2">
                <h3><?php esc_html_e('Choose a date', 'll-simple-booking'); ?></h3>
                <div class="llsba-calendar-header">
                    <button type="button" class="button" id="llsba-prev-month">&larr;</button>
                    <strong id="llsba-month-label"></strong>
                    <button type="button" class="button" id="llsba-next-month">&rarr;</button>
                </div>
                <div id="llsba-calendar-grid"></div>
                <div class="llsba-actions">
                    <button type="button" class="button" data-prev-step="1"><?php esc_html_e('Back', 'll-simple-booking'); ?></button>
                    <button type="button" class="button button-primary" data-next-step="3"><?php esc_html_e('Next', 'll-simple-booking'); ?></button>
                </div>
            </div>

            <div class="llsba-step" data-step="3">
                <h3><?php esc_html_e('Choose a time slot', 'll-simple-booking'); ?></h3>
                <p id="llsba-selected-date"></p>
                <div id="llsba-slots"></div>
                <div class="llsba-actions">
                    <button type="button" class="button" data-prev-step="2"><?php esc_html_e('Back', 'll-simple-booking'); ?></button>
                    <button type="button" class="button button-primary" id="llsba-submit-booking"><?php esc_html_e('Book Appointment', 'll-simple-booking'); ?></button>
                </div>
            </div>

            <div class="llsba-step" data-step="4">
                <h3><?php esc_html_e('Thank you', 'll-simple-booking'); ?></h3>
                <p><?php esc_html_e('Your appointment has been saved.', 'll-simple-booking'); ?></p>
            </div>

            <p id="llsba-message" aria-live="polite"></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
