<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class Self_Hosted_Manager
{
    /** @var License */
    private $license;

    public function __construct(License $license)
    {
        $this->license = $license;

        add_filter('llsba_license_validate_payload', [$this, 'validate_payload'], 10, 2);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);
        add_action('upgrader_process_complete', [$this, 'clear_update_cache'], 10, 2);
    }

    public function validate_payload($result, array $payload)
    {
        if (is_array($result)) {
            return $result;
        }

        $purchase_code = sanitize_text_field((string) ($payload['purchase_code'] ?? ''));
        $action        = sanitize_text_field((string) ($payload['action'] ?? 'check'));

        if ('' === $purchase_code) {
            return [
                'success' => false,
                'message' => __('Missing license code.', 'll-simple-booking'),
                'data'    => [],
            ];
        }

        $request = wp_remote_post($this->rest_url('/validate'), [
            'timeout'   => 20,
            'sslverify' => $this->sslverify(),
            'body'      => [
                'license_code' => $purchase_code,
                'product_slug' => $this->product_slug(),
                'domain'       => wp_parse_url(home_url(), PHP_URL_HOST),
                'action'       => $action,
            ],
        ]);

        if (is_wp_error($request)) {
            return [
                'success' => false,
                'message' => $request->get_error_message(),
                'data'    => [],
            ];
        }

        $body = json_decode((string) wp_remote_retrieve_body($request), true);
        if (! is_array($body)) {
            return [
                'success' => false,
                'message' => __('Invalid response from self-hosted license manager.', 'll-simple-booking'),
                'data'    => [],
            ];
        }

        return [
            'success' => ! empty($body['success']),
            'message' => sanitize_text_field((string) ($body['message'] ?? '')),
            'data'    => is_array($body['data'] ?? null) ? $body['data'] : [],
        ];
    }

    public function inject_update($transient)
    {
        if (! is_object($transient) || empty($transient->checked) || ! is_array($transient->checked)) {
            return $transient;
        }

        if (! $this->license->can_run()) {
            return $transient;
        }

        $plugin_file = plugin_basename(LLSBA_FILE);
        if (! isset($transient->checked[$plugin_file])) {
            return $transient;
        }

        $license_code = $this->license->get_purchase_code();
        if ('' === $license_code) {
            return $transient;
        }

        $info = $this->fetch_plugin_info($license_code);
        if (! $info['success']) {
            return $transient;
        }

        $data          = $info['data'];
        $remote_version = sanitize_text_field((string) ($data['version'] ?? ''));
        if ('' === $remote_version) {
            return $transient;
        }

        if (version_compare(LLSBA_VERSION, $remote_version, '<')) {
            $transient->response[$plugin_file] = (object) [
                'slug'         => $this->product_slug(),
                'plugin'       => $plugin_file,
                'new_version'  => $remote_version,
                'tested'       => sanitize_text_field((string) ($data['tested'] ?? '')),
                'requires'     => sanitize_text_field((string) ($data['requires'] ?? '')),
                'requires_php' => sanitize_text_field((string) ($data['requires_php'] ?? '')),
                'package'      => esc_url_raw((string) ($data['download_url'] ?? '')),
                'url'          => home_url('/'),
            ];
        } else {
            $transient->no_update[$plugin_file] = (object) [
                'slug'         => $this->product_slug(),
                'plugin'       => $plugin_file,
                'new_version'  => $remote_version,
                'tested'       => sanitize_text_field((string) ($data['tested'] ?? '')),
                'requires'     => sanitize_text_field((string) ($data['requires'] ?? '')),
                'requires_php' => sanitize_text_field((string) ($data['requires_php'] ?? '')),
                'package'      => esc_url_raw((string) ($data['download_url'] ?? '')),
                'url'          => home_url('/'),
            ];
        }

        return $transient;
    }

    public function plugin_information($result, string $action, $args)
    {
        if ('plugin_information' !== $action || ! is_object($args) || empty($args->slug) || $args->slug !== $this->product_slug()) {
            return $result;
        }

        if (! $this->license->can_run()) {
            return $result;
        }

        $license_code = $this->license->get_purchase_code();
        if ('' === $license_code) {
            return $result;
        }

        $info = $this->fetch_plugin_info($license_code);
        if (! $info['success']) {
            return $result;
        }

        $data = $info['data'];

        return (object) [
            'name'          => sanitize_text_field((string) ($data['name'] ?? 'LL Simple Booking Appointments')),
            'slug'          => $this->product_slug(),
            'version'       => sanitize_text_field((string) ($data['version'] ?? LLSBA_VERSION)),
            'author'        => 'Lievelingslinnen',
            'homepage'      => home_url('/'),
            'requires'      => sanitize_text_field((string) ($data['requires'] ?? '')),
            'requires_php'  => sanitize_text_field((string) ($data['requires_php'] ?? '')),
            'tested'        => sanitize_text_field((string) ($data['tested'] ?? '')),
            'last_updated'  => sanitize_text_field((string) ($data['last_updated'] ?? gmdate('Y-m-d H:i:s'))),
            'download_link' => esc_url_raw((string) ($data['download_url'] ?? '')),
            'sections'      => is_array($data['sections'] ?? null) ? $data['sections'] : [
                'description' => '',
                'changelog'   => '',
            ],
        ];
    }

    public function clear_update_cache($upgrader, array $options): void
    {
        if (! empty($options['type']) && 'plugin' === $options['type']) {
            delete_site_transient('update_plugins');
        }
    }

    private function fetch_plugin_info(string $license_code): array
    {
        $request = wp_remote_get(add_query_arg([
            'product_slug' => $this->product_slug(),
            'license_code' => $license_code,
        ], $this->rest_url('/plugin-info')), [
            'timeout'   => 20,
            'sslverify' => $this->sslverify(),
        ]);

        if (is_wp_error($request)) {
            return [
                'success' => false,
                'data'    => [],
            ];
        }

        $body = json_decode((string) wp_remote_retrieve_body($request), true);

        if (! is_array($body) || empty($body['success']) || ! is_array($body['data'] ?? null)) {
            return [
                'success' => false,
                'data'    => [],
            ];
        }

        return [
            'success' => true,
            'data'    => $body['data'],
        ];
    }

    private function rest_url(string $path): string
    {
        $base = (string) apply_filters('llsba_self_hosted_rest_base', rest_url('llshlm/v1'));
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    private function product_slug(): string
    {
        return sanitize_title((string) apply_filters('llsba_self_hosted_product_slug', 'll-simple-booking'));
    }

    private function sslverify(): bool
    {
        return (bool) apply_filters('llsba_self_hosted_sslverify', false);
    }
}
