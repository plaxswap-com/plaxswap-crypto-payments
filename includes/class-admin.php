<?php
/** Merchant setup and safe WooCommerce order details. */

namespace Plaxswap\WooCommerce;

defined( 'ABSPATH' ) || exit;

final class Admin {
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'adminAssets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'checkoutAssets' ) );
		add_action( 'add_meta_boxes', array( self::class, 'metaBoxes' ), 10, 2 );
		add_filter( 'woocommerce_order_actions', array( self::class, 'orderActions' ), 10, 2 );
		add_action( 'woocommerce_order_action_plaxswap_refresh', array( self::class, 'refreshOrder' ) );
	}

	public static function adminAssets(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$page    = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab     = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		if ( 'wc-settings' !== $page || 'checkout' !== $tab || 'plaxswap' !== $section ) {
			return;
		}
		wp_enqueue_style( 'plaxswap-wc-admin', PLAXSWAP_WC_URL . 'assets/admin.css', array(), PLAXSWAP_WC_VERSION );
		wp_enqueue_script( 'plaxswap-wc-admin', PLAXSWAP_WC_URL . 'assets/admin.js', array(), PLAXSWAP_WC_VERSION, true );
	}

	public static function checkoutAssets(): void {
		$checkout = function_exists( 'is_checkout' ) && is_checkout();
		$view     = function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' );
		if ( $checkout || $view ) {
			wp_enqueue_style( 'plaxswap-wc-checkout', PLAXSWAP_WC_URL . 'assets/checkout.css', array(), PLAXSWAP_WC_VERSION );
		}
	}

	public static function settings( Gateway $gateway ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$test       = 'yes' === $gateway->get_option( 'testmode', 'yes' );
		$enabled    = 'yes' === $gateway->get_option( 'enabled', 'no' );
		$connected  = (bool) ( $gateway->get_option( 'api_key' ) && $gateway->get_option( 'webhook_secret' ) && $gateway->get_option( 'merchant_id' ) );
		$checked    = $gateway->get_option( 'checked_at', '' );
		$callback   = WC()->api_request_url( 'plaxswap' );
		$dashboard  = 'https://plaxswap.com/merchants/dashboard';
		$status     = __( 'Connect your merchant account', 'plaxswap-crypto-payments' );
		$tone       = 'warning';
		if ( $connected ) {
			$status = $test ? __( 'Test mode · store managers only', 'plaxswap-crypto-payments' ) : __( 'Live credentials saved', 'plaxswap-crypto-payments' );
			$tone   = $test ? 'test' : 'ready';
		}
		if ( ! $enabled ) {
			$status = __( 'Payments are disabled', 'plaxswap-crypto-payments' );
			$tone   = 'test';
		}
		if ( ! $test && ! is_ssl() ) {
			$status = __( 'HTTPS is required for live checkout', 'plaxswap-crypto-payments' );
			$tone   = 'warning';
		}
		?>
		<section class="plaxswap-setup" aria-labelledby="plaxswap-settings-title">
			<header class="plaxswap-setup__hero">
				<div>
					<div class="plaxswap-setup__brand">
						<img class="plaxswap-setup__logo" src="<?php echo esc_url( PLAXSWAP_WC_URL . 'assets/plaxswap-logo.jpg' ); ?>" width="44" height="44" alt="" />
						<h2 id="plaxswap-settings-title"><?php esc_html_e( 'Crypto payments, connected to your store.', 'plaxswap-crypto-payments' ); ?></h2>
					</div>
					<p class="plaxswap-setup__lead"><?php esc_html_e( 'Customers choose how to pay. You follow every order in WooCommerce and manage settled funds in your Plaxswap merchant wallet.', 'plaxswap-crypto-payments' ); ?></p>
				</div>
				<div class="plaxswap-setup__actions">
					<a class="plaxswap-setup__button" href="<?php echo esc_url( $dashboard ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open merchant dashboard', 'plaxswap-crypto-payments' ); ?></a>
				</div>
			</header>
			<div class="plaxswap-setup__body">
				<p class="plaxswap-setup__status" data-tone="<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $status ); ?></p>
				<ol class="plaxswap-setup__steps">
					<li class="plaxswap-setup__step">
						<span class="plaxswap-setup__step-number" aria-hidden="true">1</span>
						<h3><?php esc_html_e( 'Connect your account', 'plaxswap-crypto-payments' ); ?></h3>
						<p><?php esc_html_e( 'Create a test integration key and copy the matching webhook signing secret. Save both in the fields below.', 'plaxswap-crypto-payments' ); ?></p>
						<p><a href="<?php echo esc_url( $dashboard . '?tab=keys' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get your merchant API key', 'plaxswap-crypto-payments' ); ?></a></p>
					</li>
					<li class="plaxswap-setup__step">
						<span class="plaxswap-setup__step-number" aria-hidden="true">2</span>
						<h3><?php esc_html_e( 'Connect payment updates', 'plaxswap-crypto-payments' ); ?></h3>
						<p><?php esc_html_e( 'Add this callback URL under Developers → Webhooks in your merchant dashboard. Signed updates let your store verify payments and update orders.', 'plaxswap-crypto-payments' ); ?></p>
						<div class="plaxswap-setup__callback">
							<label class="plaxswap-setup__sr-only" for="plaxswap-callback-url"><?php esc_html_e( 'Payment callback URL', 'plaxswap-crypto-payments' ); ?></label>
							<input id="plaxswap-callback-url" type="text" readonly value="<?php echo esc_attr( $callback ); ?>" spellcheck="false" autocomplete="off" />
							<button type="button" class="plaxswap-setup__copy" data-plaxswap-copy="plaxswap-callback-url" aria-live="polite"><?php esc_html_e( 'Copy', 'plaxswap-crypto-payments' ); ?></button>
						</div>
						<p><a href="<?php echo esc_url( $dashboard . '?tab=webhooks' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open merchant webhooks', 'plaxswap-crypto-payments' ); ?></a></p>
					</li>
					<li class="plaxswap-setup__step">
						<span class="plaxswap-setup__step-number" aria-hidden="true">3</span>
						<h3><?php esc_html_e( 'Test, then accept payments', 'plaxswap-crypto-payments' ); ?></h3>
						<p><?php esc_html_e( 'Save changes to check your connection. Place a test order as a store manager. When ready, save a live key and matching secret, then turn off test mode.', 'plaxswap-crypto-payments' ); ?></p>
						<p><a href="https://plaxswap.com/developers/payments" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Read the payment integration guide', 'plaxswap-crypto-payments' ); ?></a></p>
					</li>
				</ol>
				<div class="plaxswap-setup__details">
					<div class="plaxswap-setup__detail"><span class="plaxswap-setup__detail-label"><?php esc_html_e( 'Saved credentials', 'plaxswap-crypto-payments' ); ?></span><span class="plaxswap-setup__detail-value"><?php echo esc_html( $connected ? __( 'Encrypted on this WordPress server', 'plaxswap-crypto-payments' ) : __( 'Not connected yet', 'plaxswap-crypto-payments' ) ); ?></span></div>
					<div class="plaxswap-setup__detail"><span class="plaxswap-setup__detail-label"><?php esc_html_e( 'Last connection check', 'plaxswap-crypto-payments' ); ?></span><span class="plaxswap-setup__detail-value"><?php echo esc_html( self::date( $checked ) ); ?></span></div>
				</div>
				<table class="form-table" role="presentation">
					<?php $gateway->generate_settings_html(); ?>
				</table>
				<div class="plaxswap-setup__help">
					<p><?php esc_html_e( 'The API key connects your store to your merchant account. The signing secret verifies incoming payment updates. Neither is shown to customers. Leave a saved credential blank to keep it.', 'plaxswap-crypto-payments' ); ?></p>
					<p><?php esc_html_e( 'Settled funds arrive in your merchant wallet in the coin and network paid. The wallet holds 133 coins on 17 networks. There you can receive, send to your saved addresses and swap between coins; every fee is shown before you approve.', 'plaxswap-crypto-payments' ); ?> <a href="<?php echo esc_url( $dashboard . '?tab=wallet' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open your merchant wallet', 'plaxswap-crypto-payments' ); ?></a></p>
					<p><?php esc_html_e( 'Your public store needs HTTPS and a reachable callback for real payments. Store and payment status remain separate until settlement is verified.', 'plaxswap-crypto-payments' ); ?> <a href="https://plaxswap.com/support" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '24/7 human support', 'plaxswap-crypto-payments' ); ?></a></p>
				</div>
			</div>
		</section>
		<?php
	}

	public static function metaBoxes( $screen = '', $object = null ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		if ( ! in_array( $screen, $screens, true ) ) {
			return;
		}
		$order = self::order( $object );
		if ( $order && 'plaxswap' !== $order->get_payment_method() && ! is_array( $order->get_meta( '_pxs_invoice', true ) ) ) {
			return;
		}
		add_meta_box( 'plaxswap-order-payment', __( 'Plaxswap payment', 'plaxswap-crypto-payments' ), array( self::class, 'orderBox' ), $screen, 'side', 'default' );
	}

	public static function orderBox( $object ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$order   = self::order( $object );
		$invoice = $order ? $order->get_meta( '_pxs_invoice', true ) : null;
		if ( ! is_array( $invoice ) ) {
			echo '<p>' . esc_html__( 'No Plaxswap payment has been assigned to this order.', 'plaxswap-crypto-payments' ) . '</p>';
			return;
		}
		$summary = isset( $invoice['summary'] ) && is_array( $invoice['summary'] ) ? $invoice['summary'] : array();
		$payment = is_string( $invoice['remote_id'] ?? null ) && preg_match( '/^pay_[a-z0-9]{16}$/D', $invoice['remote_id'] ) ? $invoice['remote_id'] : '';
		$rows    = array(
			__( 'Mode', 'plaxswap-crypto-payments' ) => ! empty( $invoice['livemode'] ) ? __( 'Live', 'plaxswap-crypto-payments' ) : __( 'Test · simulated funds', 'plaxswap-crypto-payments' ),
			__( 'Payment status', 'plaxswap-crypto-payments' ) => self::status( $invoice['status'] ?? 'pending' ),
			__( 'Invoice total', 'plaxswap-crypto-payments' ) => self::scalar( $invoice['amount'] ?? '' ) . ' ' . self::scalar( $invoice['currency'] ?? '' ),
			__( 'Payment ID', 'plaxswap-crypto-payments' ) => $payment ?: __( 'Not assigned yet', 'plaxswap-crypto-payments' ),
			__( 'Created', 'plaxswap-crypto-payments' ) => self::date( $invoice['created_at'] ?? '' ),
			__( 'Last verified', 'plaxswap-crypto-payments' ) => self::date( $invoice['last_checked'] ?? '' ),
		);
		if ( ! empty( $invoice['completed_at'] ) ) {
			$rows[ __( 'Payment completed', 'plaxswap-crypto-payments' ) ] = self::date( $invoice['completed_at'] );
		}
		if ( ! empty( $invoice['last_error'] ) ) {
			$error = self::scalar( $invoice['last_error'] );
			$rows[ __( 'Last check result', 'plaxswap-crypto-payments' ) ] = preg_match( '/^[a-z0-9_:\-]{1,100}$/D', $error ) ? $error : __( 'Status could not be verified', 'plaxswap-crypto-payments' );
		}
		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td style="overflow-wrap:anywhere">' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$balances = isset( $summary['receivedBalances'] ) && is_array( $summary['receivedBalances'] ) ? $summary['receivedBalances'] : array();
		if ( $balances ) {
			echo '<h4>' . esc_html__( 'Merchant credits · exact amounts', 'plaxswap-crypto-payments' ) . '</h4><ul>';
			foreach ( array_slice( $balances, 0, 200 ) as $balance ) {
				if ( ! is_array( $balance ) || ! is_array( $balance['method'] ?? null ) ) {
					continue;
				}
				echo '<li style="overflow-wrap:anywhere"><strong>' . esc_html( self::method( $balance['method'] ) ) . '</strong><br><code style="white-space:normal">' . esc_html( self::scalar( $balance['amount'] ?? '' ) ) . '</code></li>';
			}
			echo '</ul>';
		}
		$paidWith = array();
		foreach ( array_slice( is_array( $summary['transactions'] ?? null ) ? $summary['transactions'] : array(), 0, 200 ) as $transaction ) {
			if ( is_array( $transaction ) && is_array( $transaction['payCurrency'] ?? null ) ) {
				$paidWith[] = self::method( $transaction['payCurrency'] );
			}
		}
		if ( $paidWith ) {
			echo '<p><strong>' . esc_html__( 'Paid with', 'plaxswap-crypto-payments' ) . '</strong><br>' . esc_html( implode( ', ', array_unique( $paidWith ) ) ) . '</p>';
		}
		$fee = isset( $summary['fee'] ) && is_array( $summary['fee'] ) ? $summary['fee'] : array();
		if ( isset( $fee['amount'] ) ) {
			$feeMethod = is_array( $summary['receive'] ?? null ) ? self::method( $summary['receive'] ) : '';
			echo '<p><strong>' . esc_html__( 'Platform fee · invoice basis', 'plaxswap-crypto-payments' ) . '</strong><br><span style="overflow-wrap:anywhere">' . esc_html( self::scalar( $fee['amount'] ) . ' ' . $feeMethod ) . '</span>';
			if ( in_array( $fee['paidBy'] ?? '', array( 'customer', 'merchant' ), true ) ) {
				echo '<br>' . esc_html( 'customer' === $fee['paidBy'] ? __( 'Paid by customer', 'plaxswap-crypto-payments' ) : __( 'Paid by merchant', 'plaxswap-crypto-payments' ) );
			}
			echo '<br><small>' . esc_html__( 'Calculated from the invoice total. The final payment quote includes applicable network and exchange charges.', 'plaxswap-crypto-payments' ) . '</small></p>';
		}
		if ( $payment ) {
			try {
				$url = Service::checkoutUrl( $invoice );
				echo '<p><a class="button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open payment & tracking', 'plaxswap-crypto-payments' ) . '</a></p>';
			} catch ( \Throwable $error ) {
				// Do not expose a stored URL, raw error or private invoice on a failed link check.
			}
			echo '<p>' . esc_html__( 'To verify again, choose “Refresh Plaxswap payment” in Order actions and click Update.', 'plaxswap-crypto-payments' ) . '</p>';
		}
	}

	public static function orderActions( $actions, $order = null ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $order instanceof \WC_Order ) {
			return $actions;
		}
		$invoice = $order->get_meta( '_pxs_invoice', true );
		if ( is_array( $invoice ) && ! empty( $invoice['remote_id'] ) ) {
			$actions['plaxswap_refresh'] = __( 'Refresh Plaxswap payment', 'plaxswap-crypto-payments' );
		}
		return $actions;
	}

	public static function refreshOrder( $order ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $order instanceof \WC_Order ) {
			return;
		}
		try {
			Service::reconcile( (int) $order->get_id() );
			$order->add_order_note( __( 'Plaxswap payment status was checked through the merchant API.', 'plaxswap-crypto-payments' ) );
		} catch ( \Throwable $error ) {
			Service::log( $error, (int) $order->get_id() );
			$order->add_order_note( __( 'The Plaxswap payment could not be verified. Check the payment details and try refreshing its status again.', 'plaxswap-crypto-payments' ) );
		}
	}

	private static function order( $object ) {
		if ( $object instanceof \WC_Order ) {
			return $object;
		}
		if ( $object instanceof \WP_Post ) {
			return wc_get_order( $object->ID );
		}
		return null;
	}

	private static function scalar( $value ): string {
		return is_scalar( $value ) ? substr( (string) $value, 0, 200 ) : '';
	}

	private static function method( array $method ): string {
		if ( ! empty( $method['label'] ) && is_string( $method['label'] ) ) {
			return self::scalar( $method['label'] );
		}
		return trim( self::scalar( $method['assetId'] ?? '' ) . ' · ' . self::scalar( $method['networkId'] ?? '' ), ' ·' );
	}

	private static function date( $value ): string {
		if ( ( is_int( $value ) || is_string( $value ) ) && preg_match( '/^[0-9]{1,12}$/D', (string) $value ) ) {
			$time = (int) $value;
		} elseif ( is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value ) && strlen( $value ) < 80 ) {
			$time = strtotime( $value );
		} else {
			$time = false;
		}
		return $time && $time > 0 ? wp_date( 'j M Y, H:i:s T', $time ) : __( 'Not checked yet', 'plaxswap-crypto-payments' );
	}

	private static function status( $status ): string {
		$labels = array(
			'pending'        => __( 'Awaiting payment', 'plaxswap-crypto-payments' ),
			'confirming'     => __( 'Confirming on network', 'plaxswap-crypto-payments' ),
			'partially_paid' => __( 'Partially paid', 'plaxswap-crypto-payments' ),
			'paid'           => __( 'Paid · verified', 'plaxswap-crypto-payments' ),
			'expired'        => __( 'Payment expired', 'plaxswap-crypto-payments' ),
			'cancelled'      => __( 'Payment cancelled', 'plaxswap-crypto-payments' ),
			'failed'         => __( 'Payment failed', 'plaxswap-crypto-payments' ),
		);
		return is_string( $status ) && isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Status not verified', 'plaxswap-crypto-payments' );
	}
}
