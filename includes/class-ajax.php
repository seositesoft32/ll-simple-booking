<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class Ajax
{
    /** @var Settings */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        add_action('wp_ajax_llsba_month_data', [$this, 'month_data']);
        add_action('wp_ajax_nopriv_llsba_month_data', [$this, 'month_data']);

        add_action('wp_ajax_llsba_day_slots', [$this, 'day_slots']);
        add_action('wp_ajax_nopriv_llsba_day_slots', [$this, 'day_slots']);

        add_action('wp_ajax_llsba_submit_booking', [$this, 'submit_booking']);
        add_action('wp_ajax_nopriv_llsba_submit_booking', [$this, 'submit_booking']);
    }

    public function month_data(): void
    {
        $this->check_nonce();

        $year  = absint($_POST['year'] ?? 0);
        $month = absint($_POST['month'] ?? 0);

        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            wp_send_json_error(['message' => __('Invalid month.', 'll-simple-booking')], 400);
        }

        $days_in_month = (int) wp_date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
        $calendar      = [];

        for ($day = 1; $day <= $days_in_month; $day++) {
            $date     = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $booked   = $this->count_bookings_for_date($date);
            $capacity = $this->settings->max_bookings_for_date($date);

            $calendar[$date] = [
                'booked'    => $booked,
                'capacity'  => $capacity,
                'available' => max(0, $capacity - $booked),
                'enabled'   => $capacity > 0,
            ];
        }

        wp_send_json_success([
            'days' => $calendar,
        ]);
    }

    public function day_slots(): void
    {
        $this->check_nonce();

        $date = sanitize_text_field((string) ($_POST['date'] ?? ''));

        if (! $this->is_valid_date($date)) {
            wp_send_json_error(['message' => __('Invalid date.', 'll-simple-booking')], 400);
        }

        $slots       = $this->settings->build_slots_for_date($date);
        $max_for_day = $this->settings->max_bookings_for_date($date);
        $day_booked  = $this->count_bookings_for_date($date);
        $response    = [];

        foreach ($slots as $slot) {
            $booked_on_slot = $this->count_bookings_for_slot($date, $slot);

            $response[] = [
                'time'      => $slot,
                'booked'    => $booked_on_slot,
                'available' => $booked_on_slot < 1 && $day_booked < $max_for_day,
            ];
        }

        wp_send_json_success([
            'date'     => $date,
            'booked'   => $day_booked,
            'capacity' => $max_for_day,
            'slots'    => $response,
        ]);
    }

    public function submit_booking(): void
    {
        $this->check_nonce();

        $contact = preg_replace('/[^0-9+\-\s]/', '', (string) ($_POST['contact'] ?? ''));
        $date    = sanitize_text_field((string) ($_POST['date'] ?? ''));
        $time    = sanitize_text_field((string) ($_POST['time'] ?? ''));

        if (strlen(trim((string) $contact)) < 6) {
            wp_send_json_error(['message' => __('Please enter a valid contact number.', 'll-simple-booking')], 400);
        }

        if (! $this->is_valid_date($date) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            wp_send_json_error(['message' => __('Please choose a valid date and time.', 'll-simple-booking')], 400);
        }

        $slots = $this->settings->build_slots_for_date($date);

        if (! in_array($time, $slots, true)) {
            wp_send_json_error(['message' => __('This slot is not available.', 'll-simple-booking')], 400);
        }

        $max_for_day = $this->settings->max_bookings_for_date($date);
        $day_booked  = $this->count_bookings_for_date($date);
        $slot_booked = $this->count_bookings_for_slot($date, $time);

        if ($day_booked >= $max_for_day || $slot_booked >= 1) {
            wp_send_json_error(['message' => __('This slot was just booked. Please choose another time.', 'll-simple-booking')], 409);
        }

        $post_id = wp_insert_post([
            'post_type'   => Post_Type::TYPE,
            'post_status' => 'publish',
            'post_title'  => sprintf(__('Booking %s %s', 'll-simple-booking'), $date, $time),
        ], true);

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => __('Could not save booking.', 'll-simple-booking')], 500);
        }

        update_post_meta($post_id, '_llsba_contact', sanitize_text_field((string) $contact));
        update_post_meta($post_id, '_llsba_date', $date);
        update_post_meta($post_id, '_llsba_time', $time);

        wp_send_json_success([
            'message' => __('Booking saved successfully.', 'll-simple-booking'),
        ]);
    }

    private function check_nonce(): void
    {
        if (! check_ajax_referer('llsba_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'll-simple-booking')], 403);
        }
    }

    private function is_valid_date(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function count_bookings_for_date(string $date): int
    {
        $query = new \WP_Query([
            'post_type'      => Post_Type::TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_llsba_date',
                    'value' => $date,
                ],
            ],
            'no_found_rows'  => false,
        ]);

        return (int) $query->found_posts;
    }

    private function count_bookings_for_slot(string $date, string $time): int
    {
        $query = new \WP_Query([
            'post_type'      => Post_Type::TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_llsba_date',
                    'value' => $date,
                ],
                [
                    'key'   => '_llsba_time',
                    'value' => $time,
                ],
            ],
            'no_found_rows'  => false,
        ]);

        return (int) $query->found_posts;
    }
}
