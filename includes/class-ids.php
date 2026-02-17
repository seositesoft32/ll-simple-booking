<?php

namespace LLSBA;

if (! defined('ABSPATH')) {
    exit;
}

class IDs
{
    /**
     * Encoded keys to avoid exposing sensitive identifiers directly.
     *
     * @var array<string,string>
     */
    private static $map = [
        'opt_license'       => 'bGxzYmFfbGljZW5zZV9kYXRh',
        'page_license'      => 'bGxzYmEtbGljZW5zZQ==',
        'action_activate'   => 'bGxzYmFfYWN0aXZhdGVfbGljZW5zZQ==',
        'action_deactivate' => 'bGxzYmFfZGVhY3RpdmF0ZV9saWNlbnNl',
        'nonce_activate'    => 'bGxzYmFfYWN0aXZhdGVfbGljZW5zZQ==',
        'nonce_deactivate'  => 'bGxzYmFfZGVhY3RpdmF0ZV9saWNlbnNl',
        'rest_base'         => 'aHR0cDovL2xvY2FsaG9zdC9zc3MvZXhwZXJpbWVudGFsL3dwLWpzb24vbGxzaGxtL3Yx',
    ];

    public static function get(string $key): string
    {
        $encoded = self::$map[$key] ?? '';
        if ('' === $encoded) {
            return '';
        }

        $decoded = base64_decode($encoded, true);
        return is_string($decoded) ? $decoded : '';
    }
}
