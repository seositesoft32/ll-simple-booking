<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class Settings
{
    private const OPTION_KEY = 'llsba_settings';

    /** @return array<string,mixed> */
    public function get(): array
    {
        $settings = get_option(self::OPTION_KEY, []);

        if (! is_array($settings)) {
            $settings = [];
        }

        $defaults = [
            'slot_duration' => 30,
            'start_time'    => '09:00',
            'end_time'      => '17:00',
            'days'          => [
                'monday'    => 1,
                'tuesday'   => 1,
                'wednesday' => 1,
                'thursday'  => 1,
                'friday'    => 1,
                'saturday'  => 0,
                'sunday'    => 0,
            ],
            'day_limits'    => [
                'monday'    => 16,
                'tuesday'   => 16,
                'wednesday' => 16,
                'thursday'  => 16,
                'friday'    => 16,
                'saturday'  => 0,
                'sunday'    => 0,
            ],
        ];

        return wp_parse_args($settings, $defaults);
    }

    /** @param array<string,mixed> $input */
    public function sanitize(array $input): array
    {
        $output = $this->get();

        $output['slot_duration'] = max(5, absint($input['slot_duration'] ?? 30));

        $start_time = sanitize_text_field((string) ($input['start_time'] ?? '09:00'));
        $end_time   = sanitize_text_field((string) ($input['end_time'] ?? '17:00'));

        $output['start_time'] = $this->sanitize_time($start_time, '09:00');
        $output['end_time']   = $this->sanitize_time($end_time, '17:00');

        if (isset($input['days']) && is_array($input['days'])) {
            foreach ($output['days'] as $day => $enabled) {
                $output['days'][$day] = empty($input['days'][$day]) ? 0 : 1;
            }
        } else {
            foreach ($output['days'] as $day => $enabled) {
                $output['days'][$day] = 0;
            }
        }

        if (isset($input['day_limits']) && is_array($input['day_limits'])) {
            foreach ($output['day_limits'] as $day => $limit) {
                $output['day_limits'][$day] = max(0, absint($input['day_limits'][$day] ?? 0));
            }
        }

        return $output;
    }

    public function register(): void
    {
        register_setting(
            'llsba_settings_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => function ($value) {
                    return $this->sanitize(is_array($value) ? $value : []);
                },
                'default'           => $this->get(),
            ]
        );
    }

    public function option_key(): string
    {
        return self::OPTION_KEY;
    }

    /** @return array<int,string> */
    public function build_slots_for_date(string $date): array
    {
        $settings = $this->get();
        $weekday  = strtolower(wp_date('l', strtotime($date)));

        if (empty($settings['days'][$weekday])) {
            return [];
        }

        $start = strtotime($date . ' ' . $settings['start_time']);
        $end   = strtotime($date . ' ' . $settings['end_time']);

        if (! $start || ! $end || $end <= $start) {
            return [];
        }

        $duration_seconds = (int) $settings['slot_duration'] * MINUTE_IN_SECONDS;
        $slots            = [];

        for ($time = $start; $time + $duration_seconds <= $end; $time += $duration_seconds) {
            $slots[] = wp_date('H:i', $time);
        }

        return $slots;
    }

    public function max_bookings_for_date(string $date): int
    {
        $settings = $this->get();
        $weekday  = strtolower(wp_date('l', strtotime($date)));

        if (empty($settings['days'][$weekday])) {
            return 0;
        }

        $hour_slots = count($this->build_slots_for_date($date));
        $day_limit  = (int) ($settings['day_limits'][$weekday] ?? 0);

        if ($day_limit <= 0) {
            return $hour_slots;
        }

        return min($hour_slots, $day_limit);
    }

    private function sanitize_time(string $value, string $fallback): string
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return $value;
        }

        return $fallback;
    }
}
