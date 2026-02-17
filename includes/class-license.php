<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class License
{
    private const OPTION_KEY = 'llsba_license_data';
    private const CRON_HOOK = 'llsba_license_recheck';

    public function __construct()
    {
        add_action(self::CRON_HOOK, [$this, 'scheduled_recheck']);
    }

    public function activate(string $purchase_code, string $source = 'envato'): array
    {
        $purchase_code = $this->sanitize_purchase_code($purchase_code);
        $source        = $this->sanitize_source($source);

        if ('' === $purchase_code) {
            return [
                'success' => false,
                'message' => __('Please enter a valid license code.', 'll-simple-booking'),
            ];
        }

        $response = $this->request_validation('activate', $purchase_code, $source);

        if (! $response['success']) {
            return $response;
        }

        $payload = $response['payload'];
        $data    = $this->get_data();

        $data['status']         = 'active';
        $data['purchase_code']  = $this->encrypt($purchase_code);
        $data['purchase_hash']  = wp_hash($purchase_code);
        $data['license_key']    = sanitize_text_field((string) ($payload['license_key'] ?? ''));
        $data['customer']       = sanitize_text_field((string) ($payload['customer'] ?? ''));
        $data['source']         = $this->sanitize_source((string) ($payload['source'] ?? $source));
        $data['valid_until']    = $this->sanitize_date((string) ($payload['valid_until'] ?? ''));
        $data['last_checked']   = time();
        $data['grace_until']    = time() + (7 * DAY_IN_SECONDS);
        $data['domain']         = wp_parse_url(home_url(), PHP_URL_HOST);
        $data['instance_id']    = $this->instance_id();
        $data['signature']      = $this->signature_for($data);
        $data['last_error']     = '';

        update_option(self::OPTION_KEY, $data, false);
        $this->ensure_cron();

        return [
            'success' => true,
            'message' => __('License activated successfully.', 'll-simple-booking'),
        ];
    }

    public function deactivate(): array
    {
        $data          = $this->get_data();
        $purchase_code = $this->decrypt((string) $data['purchase_code']);

        if ('' !== $purchase_code) {
            $this->request_validation('deactivate', $purchase_code, (string) $data['source']);
        }

        $data['status']        = 'inactive';
        $data['purchase_code'] = '';
        $data['purchase_hash'] = '';
        $data['license_key']   = '';
        $data['customer']      = '';
        $data['valid_until']   = '';
        $data['last_error']    = '';
        $data['last_checked']  = time();
        $data['grace_until']   = time();
        $data['signature']     = $this->signature_for($data);

        update_option(self::OPTION_KEY, $data, false);
        return [
            'success' => true,
            'message' => __('License deactivated.', 'll-simple-booking'),
        ];
    }

    public function is_active(): bool
    {
        $data = $this->get_data();

        if (! $this->is_signature_valid($data)) {
            return false;
        }

        if ('active' !== $data['status']) {
            return false;
        }

        if (! empty($data['valid_until']) && strtotime((string) $data['valid_until']) < time()) {
            return false;
        }

        return true;
    }

    public function can_run(): bool
    {
        if ($this->is_active()) {
            return true;
        }

        $data = $this->get_data();
        return (int) $data['grace_until'] > time();
    }

    public function get_data(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        return wp_parse_args($stored, [
            'status'        => 'inactive',
            'purchase_code' => '',
            'purchase_hash' => '',
            'license_key'   => '',
            'customer'      => '',
            'source'        => 'envato',
            'valid_until'   => '',
            'last_checked'  => 0,
            'grace_until'   => 0,
            'domain'        => '',
            'instance_id'   => $this->instance_id(),
            'signature'     => '',
            'last_error'    => '',
        ]);
    }

    public function masked_purchase_code(): string
    {
        $code = $this->decrypt((string) $this->get_data()['purchase_code']);
        if (strlen($code) < 8) {
            return '';
        }

        return substr($code, 0, 4) . str_repeat('*', max(0, strlen($code) - 8)) . substr($code, -4);
    }

    public function get_purchase_code(): string
    {
        return $this->decrypt((string) $this->get_data()['purchase_code']);
    }

    public function status_label(): string
    {
        $data = $this->get_data();

        if ($this->is_active()) {
            return __('Active', 'll-simple-booking');
        }

        if ((int) $data['grace_until'] > time()) {
            return __('Grace Period', 'll-simple-booking');
        }

        return __('Inactive', 'll-simple-booking');
    }

    public function maybe_recheck(): void
    {
        $data = $this->get_data();

        if ('active' !== (string) $data['status']) {
            return;
        }

        if (time() - (int) $data['last_checked'] < DAY_IN_SECONDS) {
            return;
        }

        $purchase_code = $this->decrypt((string) $data['purchase_code']);
        if ('' === $purchase_code) {
            return;
        }

        $response = $this->request_validation('check', $purchase_code, (string) $data['source']);
        $data['last_checked'] = time();

        if ($response['success']) {
            $payload = $response['payload'];
            $data['status']      = 'active';
            $data['valid_until'] = $this->sanitize_date((string) ($payload['valid_until'] ?? $data['valid_until']));
            $data['grace_until'] = time() + (7 * DAY_IN_SECONDS);
            $data['last_error']  = '';
        } else {
            $data['last_error'] = sanitize_text_field((string) $response['message']);
        }

        $data['signature'] = $this->signature_for($data);
        update_option(self::OPTION_KEY, $data, false);
    }

    public function scheduled_recheck(): void
    {
        $this->maybe_recheck();
    }

    public function ensure_cron(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK);
        }
    }

    public static function clear_cron(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    private function request_validation(string $action, string $purchase_code, string $source = 'envato'): array
    {
        $api_url = (string) apply_filters('llsba_license_api_url', '');
        $source  = $this->sanitize_source($source);

        $payload = [
            'plugin'        => 'll-simple-booking',
            'version'       => LLSBA_VERSION,
            'action'        => $action,
            'source'        => $source,
            'purchase_code' => $purchase_code,
            'license_code'  => $purchase_code,
            'site_url'      => home_url(),
            'domain'        => wp_parse_url(home_url(), PHP_URL_HOST),
            'instance_id'   => $this->instance_id(),
            'platform'      => 'wordpress',
            'php_version'   => PHP_VERSION,
            'wp_version'    => get_bloginfo('version'),
        ];

        if ('' === $api_url) {
            $filter_result = apply_filters('llsba_license_validate_payload', null, $payload);
            if (is_array($filter_result)) {
                return $this->normalize_remote_response($filter_result);
            }

            return [
                'success' => false,
                'message' => __('License server is not configured. Hook into llsba_license_api_url or llsba_license_validate_payload.', 'll-simple-booking'),
                'payload' => [],
            ];
        }

        $request = wp_remote_post($api_url, [
            'timeout'   => 15,
            'sslverify' => $this->sslverify_for_request($source, $api_url, $action),
            'headers'   => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body'      => wp_json_encode($payload),
        ]);

        if (is_wp_error($request)) {
            return [
                'success' => false,
                'message' => $request->get_error_message(),
                'payload' => [],
            ];
        }

        $body = wp_remote_retrieve_body($request);
        $json = json_decode((string) $body, true);

        if (! is_array($json)) {
            return [
                'success' => false,
                'message' => __('Invalid response from license server.', 'll-simple-booking'),
                'payload' => [],
            ];
        }

        return $this->normalize_remote_response($json);
    }

    private function normalize_remote_response(array $json): array
    {
        $success = ! empty($json['success']);

        return [
            'success' => $success,
            'message' => sanitize_text_field((string) ($json['message'] ?? ($success ? __('License valid.', 'll-simple-booking') : __('License validation failed.', 'll-simple-booking')))),
            'payload' => is_array($json['data'] ?? null) ? $json['data'] : [],
        ];
    }

    private function sanitize_purchase_code(string $code): string
    {
        $code = trim((string) wp_strip_all_tags($code));
        $code = preg_replace('/[^A-Za-z0-9\-]/', '', $code);
        return is_string($code) ? $code : '';
    }

    private function sanitize_source(string $source): string
    {
        $source = strtolower(trim((string) wp_strip_all_tags($source)));
        return in_array($source, ['envato', 'direct'], true) ? $source : 'envato';
    }

    private function sanitize_date(string $date): string
    {
        if ('' === $date) {
            return '';
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        return $date;
    }

    private function encrypt(string $plain): string
    {
        $key = hash('sha256', $this->salt('auth'));

        if (function_exists('openssl_encrypt')) {
            $iv_length = openssl_cipher_iv_length('AES-256-CBC');
            $iv        = random_bytes($iv_length);
            $cipher    = openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
            if (false !== $cipher) {
                return base64_encode($iv . '::' . $cipher);
            }
        }

        return base64_encode($plain);
    }

    private function decrypt(string $encrypted): string
    {
        $key = hash('sha256', $this->salt('auth'));

        $decoded = base64_decode($encrypted, true);
        if (false === $decoded) {
            return '';
        }

        if (strpos($decoded, '::') !== false && function_exists('openssl_decrypt')) {
            [$iv, $cipher] = explode('::', $decoded, 2);
            $plain         = openssl_decrypt($cipher, 'AES-256-CBC', $key, 0, $iv);
            return is_string($plain) ? $plain : '';
        }

        return sanitize_text_field($decoded);
    }

    private function signature_for(array $data): string
    {
        $payload = $data;
        unset($payload['signature']);

        return hash_hmac('sha256', wp_json_encode($payload), $this->salt('nonce'));
    }

    private function salt(string $scheme = 'auth'): string
    {
        if (function_exists('wp_salt')) {
            return (string) wp_salt($scheme);
        }

        $schemes = [
            'auth'        => ['AUTH_KEY', 'AUTH_SALT'],
            'secure_auth' => ['SECURE_AUTH_KEY', 'SECURE_AUTH_SALT'],
            'logged_in'   => ['LOGGED_IN_KEY', 'LOGGED_IN_SALT'],
            'nonce'       => ['NONCE_KEY', 'NONCE_SALT'],
        ];

        [$key_const, $salt_const] = $schemes[$scheme] ?? $schemes['auth'];

        $key  = defined($key_const) ? (string) constant($key_const) : '';
        $salt = defined($salt_const) ? (string) constant($salt_const) : '';

        if ('' !== $key || '' !== $salt) {
            return $key . $salt;
        }

        return hash('sha256', (string) wp_parse_url(home_url(), PHP_URL_HOST) . '|' . $scheme . '|llsba');
    }

    private function is_signature_valid(array $data): bool
    {
        $current = (string) ($data['signature'] ?? '');
        if ('' === $current) {
            return false;
        }

        return hash_equals($this->signature_for($data), $current);
    }

    private function instance_id(): string
    {
        return hash('sha256', (string) wp_parse_url(home_url(), PHP_URL_HOST));
    }

    private function sslverify_for_request(string $source, string $api_url, string $action): bool
    {
        $default = 'direct' !== $source;

        return (bool) apply_filters(
            'llsba_license_sslverify',
            $default,
            $source,
            $api_url,
            $action
        );
    }
}
