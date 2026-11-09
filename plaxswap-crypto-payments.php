<?php
/**
 * Plugin Name: Plaxswap Crypto Payments for WooCommerce
 * Plugin URI: https://plaxswap.com/merchants
 * Description: Accept Bitcoin, USDT, Ether and more with hosted checkout, verified order updates and your own Plaxswap merchant wallet.
 * Version: 1.0.2
 * Author: Plaxswap / SAMZAL LLC
 * Author URI: https://plaxswap.com
 * License: GPL-2.0-or-later
 * Text Domain: plaxswap-crypto-payments
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 9.0
 * WC tested up to: 11.2
 */
defined('ABSPATH') || exit;
define('PLAXSWAP_WC_VERSION', '1.0.2');
define('PLAXSWAP_WC_FILE', __FILE__);
define('PLAXSWAP_WC_PATH', plugin_dir_path(__FILE__));
define('PLAXSWAP_WC_URL', plugin_dir_url(__FILE__));

add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});
add_action('plugins_loaded', static function () {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', static function () {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-warning"><p>' . esc_html__('Plaxswap Crypto Payments needs WooCommerce to be active.', 'plaxswap-crypto-payments') . '</p></div>';
            }
        });
        return;
    }
    foreach (['core/Decimal.php','core/Signature.php','core/PaymentVerifier.php','class-secrets.php','class-api-client.php','class-service.php','class-gateway.php','class-admin.php'] as $file) {
        require_once PLAXSWAP_WC_PATH . 'includes/' . $file;
    }
    add_filter('woocommerce_payment_gateways', static function ($gateways) { $gateways[] = \Plaxswap\WooCommerce\Gateway::class; return $gateways; });
    \Plaxswap\WooCommerce\Service::register();
    \Plaxswap\WooCommerce\Admin::register();
});
add_action('woocommerce_blocks_loaded', static function () {
    if (class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        require_once PLAXSWAP_WC_PATH . 'includes/class-blocks.php';
        add_action('woocommerce_blocks_payment_method_type_registration', static function ($registry) {
            $registry->register(new \Plaxswap\WooCommerce\BlocksPaymentMethod());
        });
    }
});
add_filter('plugin_action_links_' . plugin_basename(__FILE__), static function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=plaxswap')) . '">' . esc_html__('Settings', 'plaxswap-crypto-payments') . '</a>');
    return $links;
});

