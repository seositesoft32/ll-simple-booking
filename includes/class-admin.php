<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class Admin
{
    /** @var Settings */
    private $settings;

    /** @var License */
    private $license;

    public function __construct(Settings $settings, License $license)
    {
        $this->settings = $settings;
        $this->license  = $license;

        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this->settings, 'register']);
        add_action('admin_init', [$this, 'guard_admin_access'], 1);
        add_action('admin_post_' . IDs::get('action_activate'), [$this, 'activate_license']);
        add_action('admin_post_' . IDs::get('action_deactivate'), [$this, 'deactivate_license']);
    }

    public function menu(): void
    {
        add_menu_page(
            __('Simple Booking', 'll-simple-booking'),
            __('Simple Booking', 'll-simple-booking'),
            'manage_options',
            'llsba-bookings',
            $this->license->can_run() ? [$this, 'bookings_page'] : [$this, 'license_page'],
            'dashicons-calendar-alt',
            57
        );

        if (! $this->license->can_run()) {
            add_submenu_page(
                'llsba-bookings',
                __('License', 'll-simple-booking'),
                __('License', 'll-simple-booking'),
                'manage_options',
                IDs::get('page_license'),
                [$this, 'license_page']
            );

            return;
        }

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

        add_submenu_page(
            'llsba-bookings',
            __('License', 'll-simple-booking'),
            __('License', 'll-simple-booking'),
            'manage_options',
            IDs::get('page_license'),
            [$this, 'license_page']
        );
    }

    public function bookings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (! $this->license->can_run()) {
            wp_die(esc_html__('License inactive. Activate your license to access bookings.', 'll-simple-booking'));
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

        if (! $this->license->can_run()) {
            wp_die(esc_html__('License inactive. Activate your license to access settings.', 'll-simple-booking'));
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

    public function license_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $data = $this->license->get_data();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('License', 'll-simple-booking'); ?></h1>

            <?php if (isset($_GET['llsba_notice'])) : ?>
                <div class="notice notice-<?php echo isset($_GET['llsba_success']) ? 'success' : 'error'; ?> is-dismissible">
                    <p><?php echo esc_html(sanitize_text_field((string) wp_unslash($_GET['llsba_notice']))); ?></p>
                </div>
            <?php endif; ?>

            <table class="widefat striped" style="max-width: 820px; margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Status', 'll-simple-booking'); ?></th>
                        <td><?php echo esc_html($this->license->status_label()); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Source', 'll-simple-booking'); ?></th>
                        <td><?php echo esc_html(ucfirst((string) ($data['source'] ?? 'envato'))); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Purchase Code', 'll-simple-booking'); ?></th>
                        <td><?php echo esc_html($this->license->masked_purchase_code() ?: __('Not saved', 'll-simple-booking')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Last checked', 'll-simple-booking'); ?></th>
                        <td><?php echo esc_html(! empty($data['last_checked']) ? wp_date('Y-m-d H:i', (int) $data['last_checked']) : __('Never', 'll-simple-booking')); ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 20px;">
                <?php wp_nonce_field(IDs::get('nonce_activate')); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(IDs::get('action_activate')); ?>" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="llsba-license-source"><?php esc_html_e('License Source', 'll-simple-booking'); ?></label></th>
                        <td>
                            <select id="llsba-license-source" name="source">
                                <option value="envato"><?php esc_html_e('ThemeForest / Envato', 'll-simple-booking'); ?></option>
                                <option value="direct"><?php esc_html_e('Direct Sale', 'll-simple-booking'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose Envato for ThemeForest purchase codes, or Direct Sale for your own license keys.', 'll-simple-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="llsba-purchase-code"><?php esc_html_e('License Code', 'll-simple-booking'); ?></label></th>
                        <td>
                            <input id="llsba-purchase-code" type="text" class="regular-text" name="purchase_code" value="" autocomplete="off" />
                            <p class="description"><?php esc_html_e('Enter your ThemeForest purchase code or direct license key.', 'll-simple-booking'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Activate License', 'll-simple-booking')); ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(IDs::get('nonce_deactivate')); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(IDs::get('action_deactivate')); ?>" />
                <?php submit_button(__('Deactivate License', 'll-simple-booking'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function activate_license(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized request.', 'll-simple-booking'));
        }

        check_admin_referer(IDs::get('nonce_activate'));

        $purchase_code = sanitize_text_field((string) wp_unslash($_POST['purchase_code'] ?? ''));
        $source        = sanitize_text_field((string) wp_unslash($_POST['source'] ?? 'envato'));
        $result        = $this->license->activate($purchase_code, $source);

        $this->redirect_license_page((string) $result['message'], ! empty($result['success']));
    }

    public function deactivate_license(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized request.', 'll-simple-booking'));
        }

        check_admin_referer(IDs::get('nonce_deactivate'));

        $result = $this->license->deactivate();
        $this->redirect_license_page((string) $result['message'], ! empty($result['success']));
    }

    private function redirect_license_page(string $message, bool $success): void
    {
        $url = add_query_arg([
            'page'          => IDs::get('page_license'),
            'llsba_notice'  => $message,
            'llsba_success' => $success ? '1' : null,
        ], admin_url('admin.php'));

        wp_safe_redirect($url);
        exit;
    }

    public function guard_admin_access(): void
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }

        if ($this->license->can_run()) {
            return;
        }

        $page      = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        $action    = isset($_REQUEST['action']) ? sanitize_key((string) wp_unslash($_REQUEST['action'])) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';

        $allowed_actions = [IDs::get('action_activate'), IDs::get('action_deactivate')];
        if (in_array($action, $allowed_actions, true)) {
            return;
        }

        if (IDs::get('page_license') === $page) {
            return;
        }

        if (Post_Type::TYPE === $post_type || 0 === strpos($page, 'llsba-')) {
            wp_safe_redirect(add_query_arg([
                'page'         => IDs::get('page_license'),
                'llsba_notice' => __('License inactive. Activate your license to unlock all plugin features.', 'll-simple-booking'),
            ], admin_url('admin.php')));
            exit;
        }
    }
}
