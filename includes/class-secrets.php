<?php
namespace Plaxswap\WooCommerce;
defined('ABSPATH') || exit;

final class Secrets
{
    private static function key(): string { return hash('sha256', wp_salt('auth') . '|plaxswap-woocommerce|v1', true); }
    public static function seal(string $plain): string
    {
        if (!function_exists('openssl_encrypt')) { throw new \RuntimeException('encryption_unavailable'); }
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'plaxswap-wc-v1');
        if ($cipher === false || strlen($tag) !== 16) { throw new \RuntimeException('encryption_failed'); }
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }
    public static function open(string $sealed): string
    {
        if (strpos($sealed, 'v1:') !== 0 || !function_exists('openssl_decrypt')) { throw new \RuntimeException('credentials_unavailable'); }
        $bytes = base64_decode(substr($sealed, 3), true);
        if ($bytes === false || strlen($bytes) < 29) { throw new \RuntimeException('credentials_unavailable'); }
        $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'plaxswap-wc-v1');
        if ($plain === false) { throw new \RuntimeException('credentials_unavailable'); }
        return $plain;
    }
}

