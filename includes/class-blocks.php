<?php
/**
 * WooCommerce Checkout Block integration.
 *
 * @package Plaxswap_WooCommerce
 */

namespace Plaxswap\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

class BlocksPaymentMethod extends AbstractPaymentMethodType {

	protected $name = 'plaxswap';

	/** @var \WC_Payment_Gateway|null */
	private $gateway;

	public function initialize() {
		$this->settings = get_option( 'woocommerce_plaxswap_settings', array() );
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			$gateways      = WC()->payment_gateways()->payment_gateways();
			$this->gateway = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
		}
	}

	public function is_active() {
		// Use the same availability checks as classic checkout, including test mode.
		return $this->gateway && $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'plaxswap-wc-blocks',
			PLAXSWAP_WC_URL . 'assets/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
			PLAXSWAP_WC_VERSION,
			true
		);
		wp_register_style(
			'plaxswap-wc-checkout',
			PLAXSWAP_WC_URL . 'assets/checkout.css',
			array(),
			PLAXSWAP_WC_VERSION
		);
		wp_enqueue_style( 'plaxswap-wc-checkout' );
		return array( 'plaxswap-wc-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title', __( 'Pay with crypto', 'plaxswap-crypto-payments' ) ),
			'description' => $this->get_setting( 'description', __( 'Choose your coin and network at Plaxswap checkout.', 'plaxswap-crypto-payments' ) ),
			'icon'        => esc_url_raw( PLAXSWAP_WC_URL . 'assets/plaxswap-logo.jpg' ),
			'testMode'    => 'yes' === $this->get_setting( 'testmode', 'yes' ),
			'supports'    => array( 'products' ),
		);
	}
}
