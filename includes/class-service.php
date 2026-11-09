<?php
namespace Plaxswap\WooCommerce;
use Plaxswap\WooCommerce\Core\Decimal;
use Plaxswap\WooCommerce\Core\PaymentVerifier;
use Plaxswap\WooCommerce\Core\Signature;
defined('ABSPATH') || exit;

final class Service
{
    public static function register(): void
    {
        add_action('woocommerce_api_plaxswap', [self::class,'callback']);
        add_action('plaxswap_wc_reconcile', [self::class,'job'], 10, 1);
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', [self::class,'legacyInvoiceQuery'], 10, 2);
        add_action('woocommerce_thankyou_plaxswap', [self::class,'customerCard']);
        add_action('woocommerce_order_details_after_order_table', [self::class,'orderCard']);
        add_action('admin_init', static function () {
            if (function_exists('wp_add_privacy_policy_content')) {
                wp_add_privacy_policy_content('Plaxswap Crypto Payments', '<p>' . esc_html__('When you choose Plaxswap, we send the order total, currency, a purchase reference and return links to plaxswap.com to create and verify your payment. The checkout receives your browser request. Sharing your billing email from this store is optional and off by default. Payment references, statuses and reconciliation records are stored with your order. Plaxswap’s privacy notice is available at https://plaxswap.com/legal/privacy.', 'plaxswap-crypto-payments') . '</p>');
            }
        });
    }
    private static function locked(int $id, callable $work)
    {
        global $wpdb;
        $name = 'pxs_wc_' . substr(hash('sha256', $wpdb->prefix . '|' . home_url() . '|' . $id), 0, 48);
        $held = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, 5));
        if ((string) $held !== '1') { throw new \RuntimeException('order_busy'); }
        try { return $work(); }
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); }
    }
    private static function freshOrder(int $id): \WC_Order
    {
        // Bypass per-request order objects and invalidate datastore caches before financial state checks.
        if (class_exists('Automattic\\WooCommerce\\Caches\\OrderCache')) {
            wc_get_container()->get(\Automattic\WooCommerce\Caches\OrderCache::class)->remove($id);
        }
        $probe = wc_get_order($id);
        if ($probe) {
            $store = $probe->get_data_store();
            if (method_exists($store->get_current_class_name(), 'clear_cached_data')) { $store->clear_cached_data([$id]); }
            else { clean_post_cache($id); }
        }
        $order = $probe ? new \WC_Order($id) : false;
        if (!$order || $order->get_type() !== 'shop_order' || $order->get_payment_method() !== 'plaxswap') { throw new \RuntimeException('invalid_order'); }
        $order->read_meta_data(true);
        return $order;
    }
    public static function create(\WC_Order $order, array $settings): array
    {
        return self::locked((int) $order->get_id(), static function () use ($order,$settings) {
            $order = self::freshOrder((int) $order->get_id());
            $invoice = $order->get_meta('_pxs_invoice', true);
            if (is_array($invoice) && !empty($invoice['order_ref'])) {
                self::assertOrder($order, $invoice);
                if (!empty($invoice['completed_at'])) { return $invoice; }
                if (!in_array($invoice['status'], ['pending','confirming','partially_paid'], true)) { throw new \RuntimeException('payment_closed'); }
                if (!empty($invoice['remote_id'])) { return $invoice; }
            } else {
                if (!in_array($order->get_status(), ['pending','failed'], true) || $order->is_paid()) { throw new \RuntimeException('order_not_payable'); }
                $client = new ApiClient(Secrets::open((string) ($settings['api_key'] ?? '')));
                $live = $client->isLive();
                if ($live === (($settings['testmode'] ?? 'yes') === 'yes')) { throw new \RuntimeException('wrong_mode'); }
                if (!$live && !current_user_can('manage_woocommerce')) { throw new \RuntimeException('test_admin_required'); }
                if ($live && !is_ssl()) { throw new \RuntimeException('https_required'); }
                $merchant = $client->merchant();
                if (($settings['merchant_id'] ?? '') !== $merchant['id']) { throw new \RuntimeException('wrong_merchant'); }
                $amount = Decimal::normalize($order->get_total()); $currency = $order->get_currency();
                if (!Decimal::positive($amount) || strlen($amount) > 30 || !Decimal::currencySupported($currency)) { throw new \RuntimeException('invalid_order_amount'); }
                $ref = 'WC-' . substr(hash('sha256', home_url() . '|' . $order->get_id() . '|' . random_bytes(24)), 0, 40);
                $verification = bin2hex(random_bytes(24));
                $return = $order->get_checkout_order_received_url();
                $body = ['amount'=>$amount,'currency'=>$currency,'receiveMode'=>'original','orderId'=>$ref,
                    'description'=>sprintf('Order %s · %s', $order->get_order_number(), mb_substr(wp_strip_all_tags(get_bloginfo('name')),0,100)),
                    'returnUrl'=>$return,'cancelUrl'=>$return,'expiresInMinutes'=>60,'customerPaysFee'=>true,
                    'metadata'=>['integration'=>'woocommerce','wc_order'=>(string) $order->get_id(),'wc_verification'=>$verification]];
                if (($settings['share_email'] ?? 'no') === 'yes' && $order->get_billing_email()) { $body['customer'] = ['email'=>$order->get_billing_email()]; }
                $invoice = ['order_id'=>(int) $order->get_id(),'remote_id'=>'','merchant_id'=>$merchant['id'],'livemode'=>$live,
                    'amount'=>$amount,'currency'=>$currency,'order_ref'=>$ref,'verification_ref'=>$verification,
                    'api_key'=>$settings['api_key'],'webhook_secret'=>$settings['webhook_secret'],'request'=>$body,
                    'status'=>'pending','created_at'=>time(),'completed_at'=>0,'last_checked'=>0,'last_error'=>'','summary'=>[],'events'=>[],
                    'test_authorized'=>!$live && current_user_can('manage_woocommerce')];
                // Persist before the request. A timeout can safely repeat this exact body and idempotency key.
                $order->update_meta_data('_pxs_invoice', $invoice); $order->save();
                $stored = self::freshOrder((int)$order->get_id())->get_meta('_pxs_invoice',true);
                if (!is_array($stored) || ($stored['order_ref'] ?? '') !== $ref || ($stored['request'] ?? null) !== $body) { throw new \RuntimeException('invoice_not_saved'); }
                self::schedule($order->get_id(), 90);
            }
            try {
                $payment = self::clientFor($invoice)->request('POST','/api/v1/payments', $invoice['request'], $invoice['order_ref']);
                return self::accept($order, $invoice, $payment);
            } catch (\Throwable $error) { self::recordError($order, $invoice, $error); throw $error; }
        });
    }
    private static function assertOrder(\WC_Order $order, array $invoice): void
    {
        if ((int) ($invoice['order_id'] ?? 0) !== (int) $order->get_id() || ($invoice['currency'] ?? '') !== $order->get_currency()
            || !Decimal::equal($invoice['amount'] ?? null, $order->get_total())) { throw new \RuntimeException('order_changed'); }
        if (empty($invoice['livemode']) && empty($invoice['test_authorized'])) { throw new \RuntimeException('test_admin_required'); }
    }
    private static function clientFor(array $invoice): ApiClient
    {
        $settings = get_option('woocommerce_plaxswap_settings', []);
        if (($settings['merchant_id'] ?? '') === $invoice['merchant_id'] && !empty($settings['api_key'])) {
            try { $client = new ApiClient(Secrets::open($settings['api_key'])); if ($client->isLive() === (bool) $invoice['livemode']) { return $client; } }
            catch (\Throwable $ignored) { /* The order's original sealed credential remains available for pending invoices. */ }
        }
        return new ApiClient(Secrets::open($invoice['api_key'] ?? ''));
    }
    public static function checkoutUrl(array $invoice): string
    {
        if (!preg_match('/^pay_[a-z0-9]{16}$/D', $invoice['remote_id'] ?? '')) { return ''; }
        return ApiClient::origin((bool) $invoice['livemode']) . '/track/' . $invoice['remote_id'];
    }
    public static function reconcile(int $id): array
    {
        return self::locked($id, static function () use ($id) { return self::reconcileUnlocked(self::freshOrder($id)); });
    }
    private static function reconcileUnlocked(\WC_Order $order): array
    {
        $invoice = $order->get_meta('_pxs_invoice', true);
        if (!is_array($invoice) || empty($invoice['order_ref'])) { throw new \RuntimeException('invoice_not_found'); }
        if (!empty($invoice['completed_at'])) { return $invoice; }
        try {
            self::assertOrder($order, $invoice);
            $client = self::clientFor($invoice);
            $payment = !empty($invoice['remote_id']) ? $client->request('GET','/api/v1/payments/' . $invoice['remote_id'])
                : $client->request('POST','/api/v1/payments', $invoice['request'], $invoice['order_ref']);
            return self::accept($order, $invoice, $payment);
        } catch (\Throwable $error) { self::recordError($order,$invoice,$error); throw $error; }
    }
    private static function accept(\WC_Order $order, array $invoice, array $payment): array
    {
        // Store edits do not use our named lock. Re-read after the HTTP wait so cancellation/total changes win.
        $order = self::freshOrder((int)$order->get_id());
        $latest = $order->get_meta('_pxs_invoice',true);
        if (!is_array($latest) || ($latest['order_ref'] ?? '') !== $invoice['order_ref']) { throw new \RuntimeException('order_changed'); }
        $invoice = $latest;
        PaymentVerifier::validate($payment, $invoice); self::assertOrder($order,$invoice);
        $before = $invoice['status'];
        $invoice['remote_id'] = $payment['id']; $invoice['status'] = $payment['status'];
        $invoice['summary'] = PaymentVerifier::summary($payment); $invoice['last_checked'] = time(); $invoice['last_error'] = '';
        unset($invoice['request']); // Once the remote ID is known, the create payload and optional email are no longer needed.
        $order->update_meta_data('_pxs_payment_id', $payment['id']);
        if ($payment['status'] === 'paid') {
            $transaction = 'pxs_' . $payment['id'];
            if ($order->is_paid() && $order->get_transaction_id() === $transaction) {
                $invoice['completed_at'] = time();
            } elseif ($order->is_paid() || $order->has_status(['cancelled','refunded','failed']) || !empty($payment['late'])) {
                // Funds are settled, but a closed/late order needs stock and fulfilment review by the store.
                $invoice['last_error'] = 'manual_review_required';
                if ($before !== 'paid') { $order->add_order_note(__('Plaxswap: funds settled after this order closed or arrived late. Review the payment and fulfilment before completing the order.', 'plaxswap-crypto-payments')); }
            } else {
                $order->payment_complete($transaction);
                $order = self::freshOrder((int)$order->get_id());
                if ($order->is_paid() && $order->get_transaction_id() === $transaction) {
                    $invoice['completed_at'] = time();
                    /* translators: %s: verified Plaxswap payment reference. */
                    $order->add_order_note(sprintf(__('Plaxswap payment verified: %s. Merchant credit and fees are recorded below.', 'plaxswap-crypto-payments'), $payment['id']));
                } else {
                    $invoice['last_error'] = 'manual_review_required';
                    if ($before !== 'paid') { $order->add_order_note(__('Plaxswap: funds settled, but WooCommerce could not complete this order. Review its status and payment hooks before fulfilment.', 'plaxswap-crypto-payments')); }
                }
            }
            if (!empty($invoice['completed_at'])) { unset($invoice['api_key']); }
        } elseif (!$order->is_paid() && !$order->has_status(['cancelled','refunded','completed','failed'])) {
            $notes = ['pending'=>__('Plaxswap: awaiting crypto payment.', 'plaxswap-crypto-payments'),
                'confirming'=>__('Plaxswap: payment detected; waiting for verification and merchant credit.', 'plaxswap-crypto-payments'),
                'partially_paid'=>__('Plaxswap: partial payment received. The order remains unpaid; the buyer can follow the payment link for the remaining amount.', 'plaxswap-crypto-payments'),
                'expired'=>__('Plaxswap: payment expired without a complete payment. Any late funds will be flagged for review.', 'plaxswap-crypto-payments'),
                'cancelled'=>__('Plaxswap: payment cancelled before completion.', 'plaxswap-crypto-payments'),
                'failed'=>__('Plaxswap: payment failed. The order has not been marked paid.', 'plaxswap-crypto-payments')];
            $target = in_array($payment['status'], ['expired','failed'], true) ? 'failed' : ($payment['status'] === 'cancelled' ? 'cancelled' : 'on-hold');
            if ($order->get_status() !== $target) { $order->update_status($target, $notes[$payment['status']]); }
            elseif ($before !== $payment['status']) { $order->add_order_note($notes[$payment['status']]); }
        }
        $order->update_meta_data('_pxs_payment_id', $payment['id']);
        $order->update_meta_data('_pxs_invoice', $invoice); $order->save();
        $stored = self::freshOrder((int)$order->get_id())->get_meta('_pxs_invoice',true);
        foreach (['order_ref','remote_id','status','completed_at'] as $key) {
            if (!is_array($stored) || ($stored[$key] ?? null) !== $invoice[$key]) { throw new \RuntimeException('invoice_not_saved'); }
        }
        if (empty($invoice['completed_at']) && $payment['status'] !== 'paid') { self::schedule($order->get_id(), 300); }
        return $invoice;
    }
    public static function handleWebhook(string $raw, string $header, string $method = 'POST'): array
    {
        if ($method !== 'POST') { return ['status'=>405,'body'=>['error'=>'method_not_allowed']]; }
        if (strlen($raw) > 262144) { return ['status'=>413,'body'=>['error'=>'payload_too_large']]; }
        try { $event = Signature::decodeEvent($raw); }
        catch (\Throwable $error) { return ['status'=>400,'body'=>['error'=>'invalid_event']]; }
        $settings = get_option('woocommerce_plaxswap_settings', []); $secrets = [];
        try { $secrets[] = Secrets::open($settings['webhook_secret'] ?? ''); } catch (\Throwable $ignored) {}
        $id = $event['data']['id'] ?? '';
        $orders = [];
        if (is_string($id) && preg_match('/^pay_[a-z0-9]{16}$/D',$id)) {
            $args = ['limit'=>2,'payment_method'=>'plaxswap','return'=>'objects'];
            if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) { $args['meta_query'] = [['key'=>'_pxs_payment_id','value'=>$id]]; }
            else { $args['plaxswap_payment_id'] = $id; }
            $orders = wc_get_orders($args);
        }
        if (count($orders) > 1) { return ['status'=>409,'body'=>['error'=>'ambiguous_payment']]; }
        $order = $orders ? $orders[0] : null; $invoice = $order ? $order->get_meta('_pxs_invoice', true) : null;
        if (is_array($invoice)) { try { $secrets[] = Secrets::open($invoice['webhook_secret'] ?? ''); } catch (\Throwable $ignored) {} }
        if (!Signature::verify($raw, $header, $secrets, time())) { return ['status'=>403,'body'=>['error'=>'invalid_signature']]; }
        if ($event['event'] === 'ping') {
            if (($event['data']['merchantId'] ?? '') !== ($settings['merchant_id'] ?? '') || $event['livemode'] !== (($settings['testmode'] ?? 'yes') !== 'yes')) { return ['status'=>403,'body'=>['error'=>'wrong_merchant_or_mode']]; }
            return ['status'=>200,'body'=>['received'=>true]];
        }
        if (!$order || !is_array($invoice)) { return ['status'=>200,'body'=>['received'=>true,'matched'=>false]]; }
        if ($event['livemode'] !== (bool) $invoice['livemode']) { return ['status'=>403,'body'=>['error'=>'wrong_mode']]; }
        try {
            self::locked((int) $order->get_id(), static function () use ($order,$event,$raw) {
                $fresh = self::freshOrder((int) $order->get_id()); $saved = $fresh->get_meta('_pxs_invoice', true);
                $hash = hash('sha256',$raw); $events = $saved['events'] ?? [];
                if (isset($events[$event['id']])) {
                    if (!hash_equals($events[$event['id']], $hash)) { throw new \RuntimeException('event_conflict'); }
                    return;
                }
                // Signed notifications trigger an authenticated API read. Posted status/amounts never fulfil an order.
                $saved = self::reconcileUnlocked($fresh);
                $fresh = self::freshOrder((int)$order->get_id());
                $events[$event['id']] = $hash; $saved['events'] = array_slice($events, -100, null, true);
                $fresh->update_meta_data('_pxs_invoice',$saved); $fresh->save();
                $stored = self::freshOrder((int)$order->get_id())->get_meta('_pxs_invoice',true);
                if (($stored['events'][$event['id']] ?? null) !== $hash) { throw new \RuntimeException('invoice_not_saved'); }
            });
            return ['status'=>200,'body'=>['received'=>true]];
        } catch (\Throwable $error) { self::log($error, (int) $order->get_id()); return ['status'=>503,'body'=>['error'=>'verification_will_retry']]; }
    }
    public static function callback(): void
    {
        $raw = (string) file_get_contents('php://input', false, null, 0, 262145);
        $result = self::handleWebhook($raw, (string) ($_SERVER['HTTP_X_PX_SIGNATURE'] ?? ''), strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
        nocache_headers(); if ($result['status'] === 405) { header('Allow: POST'); }
        wp_send_json($result['body'], $result['status']);
    }
    public static function legacyInvoiceQuery(array $query, array $vars): array
    {
        if (isset($vars['plaxswap_payment_id']) && is_string($vars['plaxswap_payment_id']) && preg_match('/^pay_[a-z0-9]{16}$/D',$vars['plaxswap_payment_id'])) {
            $query['meta_query'][] = ['key'=>'_pxs_payment_id','value'=>$vars['plaxswap_payment_id'],'compare'=>'='];
        }
        return $query;
    }
    private static function schedule(int $id, int $delay): void
    {
        $order = wc_get_order($id); $invoice = $order ? $order->get_meta('_pxs_invoice',true) : [];
        if (!is_array($invoice) || !empty($invoice['completed_at']) || ($invoice['status'] ?? '') === 'paid' || time()-(int)($invoice['created_at'] ?? 0) >= 30*DAY_IN_SECONDS) { return; }
        if (function_exists('as_schedule_single_action')) {
            // A running action must be allowed to enqueue its successor. Only another pending action suppresses it.
            $pending = as_get_scheduled_actions(['hook'=>'plaxswap_wc_reconcile','args'=>['order_id'=>$id],'group'=>'plaxswap','status'=>'pending','per_page'=>1], 'ids');
            if (!$pending) { as_schedule_single_action(time()+$delay, 'plaxswap_wc_reconcile', ['order_id'=>$id], 'plaxswap', false); }
        } elseif (!wp_next_scheduled('plaxswap_wc_reconcile', [$id])) { wp_schedule_single_event(time()+$delay,'plaxswap_wc_reconcile',[$id]); }
    }
    public static function job($id): void
    {
        $id = (int) $id;
        try {
            $invoice = self::reconcile($id);
            if (empty($invoice['completed_at']) && $invoice['status'] !== 'paid' && time()-(int) $invoice['created_at'] < 30*DAY_IN_SECONDS) { self::schedule($id,300); }
        } catch (\Throwable $error) {
            self::log($error,$id); $order = wc_get_order($id); $invoice = $order ? $order->get_meta('_pxs_invoice',true) : [];
            if (is_array($invoice) && !empty($invoice['created_at']) && time()-(int) $invoice['created_at'] < 30*DAY_IN_SECONDS) { self::schedule($id,300); }
        }
    }
    private static function recordError(\WC_Order $order, array $invoice, \Throwable $error): void
    {
        $order = self::freshOrder((int)$order->get_id());
        $current = $order->get_meta('_pxs_invoice',true);
        if (is_array($current)) { $invoice = $current; }
        $invoice['last_checked'] = time(); $invoice['last_error'] = self::errorCode($error);
        $order->update_meta_data('_pxs_invoice',$invoice); $order->save(); self::schedule($order->get_id(),300);
        self::log($error,(int)$order->get_id());
    }
    private static function errorCode(\Throwable $error): string
    {
        $allowed = ['invalid_api_key','invalid_sandbox_origin','invalid_path','connection_unavailable','response_too_large','invalid_response','merchant_not_ready','wrong_mode','invalid_webhook_secret','new_merchant_secret_required','encryption_unavailable','encryption_failed','credentials_unavailable','order_busy','invalid_order','invoice_not_found','invoice_not_saved','order_changed','test_admin_required','https_required','wrong_merchant','invalid_order_amount','payment_closed','order_not_payable','event_conflict','invalid_payment_id','wrong_amount','wrong_currency','wrong_reference','wrong_metadata','wrong_receiving_mode','invalid_status','payment_not_final','no_merchant_credit','unauthorized','rate_limited','idempotency_conflict'];
        return in_array($error->getMessage(),$allowed,true) ? $error->getMessage() : 'verification_failed';
    }
    public static function log(\Throwable $error, int $id): void
    {
        $settings = get_option('woocommerce_plaxswap_settings',[]);
        if (($settings['debug'] ?? 'no') === 'yes' && function_exists('wc_get_logger')) { wc_get_logger()->warning(self::errorCode($error),['source'=>'plaxswap','order_id'=>$id]); }
    }
    public static function orderCard($order): void
    {
        if ($order instanceof \WC_Order && $order->get_payment_method() === 'plaxswap' && !did_action('woocommerce_thankyou_plaxswap')) { self::customerCard((int)$order->get_id()); }
    }
    public static function customerCard($id): void
    {
        static $rendered = [];
        if (isset($rendered[(int)$id])) { return; }
        $order = wc_get_order((int)$id);
        if (!$order || $order->get_payment_method() !== 'plaxswap') { return; }
        // Woo's thank-you and view-order templates already check access; retain an explicit check for this callback too.
        $orderKey = isset($_GET['key']) && is_string($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $owner = get_current_user_id() > 0 && (int)$order->get_customer_id() === get_current_user_id();
        if (!current_user_can('manage_woocommerce') && !$owner && ($orderKey === '' || !hash_equals($order->get_order_key(),$orderKey))) { return; }
        try { $invoice = self::reconcile((int)$id); } catch (\Throwable $ignored) { $invoice = $order->get_meta('_pxs_invoice',true); }
        if (!is_array($invoice) || empty($invoice['remote_id'])) { return; }
        $rendered[(int)$id] = true;
        $labels = ['pending'=>__('Awaiting payment','plaxswap-crypto-payments'),'confirming'=>__('Payment confirming','plaxswap-crypto-payments'),'partially_paid'=>__('Partial payment received','plaxswap-crypto-payments'),'paid'=>__('Payment received','plaxswap-crypto-payments'),'expired'=>__('Payment expired','plaxswap-crypto-payments'),'cancelled'=>__('Payment cancelled','plaxswap-crypto-payments'),'failed'=>__('Payment failed','plaxswap-crypto-payments')];
        echo '<section class="plaxswap-checkout"><div class="plaxswap-checkout__brand"><img class="plaxswap-checkout__logo" src="' . esc_url(PLAXSWAP_WC_URL . 'assets/plaxswap-logo.jpg') . '" alt="" />' . esc_html($labels[$invoice['status']] ?? __('Crypto payment','plaxswap-crypto-payments')) . '</div>';
        if (empty($invoice['livemode'])) { echo '<p class="plaxswap-checkout__badge">' . esc_html__('Test payment · no real funds','plaxswap-crypto-payments') . '</p>'; }
        echo '<p>' . esc_html__('Follow your coin, network, amount and confirmation status on the payment page. Returning to this store does not confirm a payment.','plaxswap-crypto-payments') . '</p><a class="button" href="' . esc_url(self::checkoutUrl($invoice)) . '">' . esc_html__('Track payment','plaxswap-crypto-payments') . '</a></section>';
    }
}
