<?php
namespace Plaxswap\WooCommerce;
use Plaxswap\WooCommerce\Core\Decimal;
defined('ABSPATH') || exit;

final class Gateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'plaxswap'; $this->has_fields = false;
        $this->method_title = __('Plaxswap Crypto Payments', 'plaxswap-crypto-payments');
        $this->method_description = __('Accept crypto through Plaxswap. Verified payments update your WooCommerce orders.', 'plaxswap-crypto-payments');
        $this->icon = PLAXSWAP_WC_URL . 'assets/plaxswap-logo.jpg'; $this->supports = ['products'];
        $this->init_form_fields(); $this->init_settings();
        $this->title = $this->get_option('title', __('Crypto · Plaxswap', 'plaxswap-crypto-payments'));
        $this->description = $this->get_option('description', __('Pay with your preferred supported coin and network. Review all charges before sending.', 'plaxswap-crypto-payments'));
        $this->enabled = $this->get_option('enabled', 'no');
        add_action('woocommerce_update_options_payment_gateways_plaxswap', [$this,'process_admin_options']);
    }
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled'=>['title'=>__('Enable payments','plaxswap-crypto-payments'),'type'=>'checkbox','label'=>__('Show Plaxswap at checkout','plaxswap-crypto-payments'),'default'=>'no'],
            'testmode'=>['title'=>__('Payment mode','plaxswap-crypto-payments'),'type'=>'checkbox','label'=>__('Test mode — checkout is visible to store managers only','plaxswap-crypto-payments'),'default'=>'yes','description'=>__('Use a test key to rehearse payments without real funds. Switch this off and save a live key for customer payments.','plaxswap-crypto-payments')],
            'api_key'=>['title'=>__('Merchant API key','plaxswap-crypto-payments'),'type'=>'plaxswap_secret','description'=>__('Create a key in Merchant dashboard → Developers → API keys. Leave blank to keep your saved key.','plaxswap-crypto-payments')],
            'webhook_secret'=>['title'=>__('Webhook signing secret','plaxswap-crypto-payments'),'type'=>'plaxswap_secret','description'=>__('Copy the signing secret from the same merchant account. This is different from your API key. Leave blank to keep it.','plaxswap-crypto-payments')],
            'title'=>['title'=>__('Checkout title','plaxswap-crypto-payments'),'type'=>'text','default'=>'Crypto · Plaxswap','desc_tip'=>true,'description'=>__('The payment method name customers see.','plaxswap-crypto-payments')],
            'description'=>['title'=>__('Checkout description','plaxswap-crypto-payments'),'type'=>'textarea','default'=>'Pay with your preferred supported coin and network. Review all charges before sending.'],
            'share_email'=>['title'=>__('Customer privacy','plaxswap-crypto-payments'),'type'=>'checkbox','label'=>__('Share the order billing email with Plaxswap for this payment','plaxswap-crypto-payments'),'default'=>'no','description'=>__('Optional and off by default. Disclose this sharing in your store privacy notice if enabled.','plaxswap-crypto-payments')],
            'debug'=>['title'=>__('Diagnostics','plaxswap-crypto-payments'),'type'=>'checkbox','label'=>__('Record payment IDs and safe error codes in WooCommerce logs','plaxswap-crypto-payments'),'default'=>'no'],
        ];
    }
    public function is_available()
    {
        if (!parent::is_available() || !$this->get_option('api_key') || !$this->get_option('merchant_id') || !$this->get_option('webhook_secret') || !function_exists('openssl_encrypt')) { return false; }
        if ($this->get_option('testmode', 'yes') === 'yes' && !current_user_can('manage_woocommerce')) { return false; }
        if (function_exists('get_woocommerce_currency') && !Decimal::currencySupported(get_woocommerce_currency())) { return false; }
        if ($this->get_option('testmode', 'yes') !== 'yes' && !is_ssl()) { return false; }
        return true;
    }
    public function payment_fields()
    {
        echo '<div class="plaxswap-checkout"><div class="plaxswap-checkout__brand"><img class="plaxswap-checkout__logo" src="' . esc_url($this->icon) . '" alt="" />' . esc_html__('Pay with crypto', 'plaxswap-crypto-payments') . '</div><p class="plaxswap-checkout__description">' . esc_html($this->description) . '</p><p class="plaxswap-checkout__hint">' . esc_html__('You will choose your coin and network on the secure payment page. Platform and applicable network charges are shown before you pay.', 'plaxswap-crypto-payments') . '</p>';
        if ($this->get_option('testmode', 'yes') === 'yes') { echo '<span class="plaxswap-checkout__badge">' . esc_html__('Test payment · no real funds', 'plaxswap-crypto-payments') . '</span>'; }
        echo '</div>';
    }
    public function process_payment($order_id)
    {
        try {
            $order = wc_get_order($order_id);
            if (!$order || $this->enabled !== 'yes') { throw new \RuntimeException('gateway_unavailable'); }
            if ($this->get_option('testmode','yes') === 'yes' && !current_user_can('manage_woocommerce')) { throw new \RuntimeException('test_admin_required'); }
            if ($this->get_option('testmode','yes') !== 'yes' && !is_ssl()) { throw new \RuntimeException('https_required'); }
            $invoice = Service::create($order, $this->settings);
            return ['result'=>'success','redirect'=>Service::checkoutUrl($invoice)];
        } catch (\Throwable $error) {
            Service::log($error, (int) $order_id);
            wc_add_notice(__('The crypto payment could not be opened. Your order has not been charged. Please try again or contact the store.', 'plaxswap-crypto-payments'), 'error');
            return ['result'=>'failure'];
        }
    }
    public function process_admin_options()
    {
        if (!current_user_can('manage_woocommerce')) { return false; }
        check_admin_referer('woocommerce-settings');
        $posted = wp_unslash($_POST); $new = $this->settings;
        try {
            foreach (['enabled','testmode','share_email','debug'] as $field) { $new[$field] = isset($posted[$this->get_field_key($field)]) ? 'yes' : 'no'; }
            $new['title'] = sanitize_text_field($posted[$this->get_field_key('title')] ?? 'Crypto · Plaxswap');
            $new['description'] = sanitize_textarea_field($posted[$this->get_field_key('description')] ?? '');
            $key = trim((string) ($posted[$this->get_field_key('api_key')] ?? ''));
            $secret = trim((string) ($posted[$this->get_field_key('webhook_secret')] ?? ''));
            if ($key !== '') { $new['api_key'] = Secrets::seal($key); }
            if ($secret !== '') { $new['webhook_secret'] = Secrets::seal($secret); }
            if ($new['enabled'] === 'yes' || $key !== '' || $secret !== '' || ($new['testmode'] ?? 'yes') !== ($this->settings['testmode'] ?? 'yes')) {
                $client = new ApiClient(Secrets::open($new['api_key'] ?? ''));
                if ($client->isLive() === ($new['testmode'] === 'yes')) { throw new ApiException('wrong_mode'); }
                $signing = Secrets::open($new['webhook_secret'] ?? '');
                if (!preg_match('/^whsec_[A-Za-z0-9_-]{24,100}$/D', $signing)) { throw new ApiException('invalid_webhook_secret'); }
                $merchant = $client->merchant();
                // A new merchant needs its own signing secret rather than accidentally keeping the old account's secret.
                if (!empty($new['merchant_id']) && $new['merchant_id'] !== $merchant['id'] && $secret === '') { throw new ApiException('new_merchant_secret_required'); }
                $new['merchant_id'] = $merchant['id']; $new['checked_at'] = time();
            }
            update_option($this->get_option_key(), $new, false); $this->settings = $new; $this->enabled = $new['enabled'];
            return true;
        } catch (\Throwable $error) {
            $message = __('Settings were not changed. Check that the API key matches your payment mode, the merchant setup is complete and the webhook secret belongs to that account.', 'plaxswap-crypto-payments');
            \WC_Admin_Settings::add_error($message); Service::log($error, 0); return false;
        }
    }
    public function generate_plaxswap_secret_html($key, $data)
    {
        $field = $this->get_field_key($key); $saved = !empty($this->settings[$key]);
        return '<tr valign="top"><th scope="row" class="titledesc"><label for="' . esc_attr($field) . '">' . esc_html($data['title']) . '</label></th><td class="forminp"><input type="password" class="input-text regular-input" name="' . esc_attr($field) . '" id="' . esc_attr($field) . '" value="" autocomplete="new-password" spellcheck="false" placeholder="' . esc_attr($saved ? __('Saved securely — leave blank to keep', 'plaxswap-crypto-payments') : __('Paste your credential', 'plaxswap-crypto-payments')) . '" /><p class="description">' . esc_html($data['description']) . '</p></td></tr>';
    }
    public function admin_options() { Admin::settings($this); }
}
