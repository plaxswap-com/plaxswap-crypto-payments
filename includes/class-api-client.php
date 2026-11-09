<?php
namespace Plaxswap\WooCommerce;
defined('ABSPATH') || exit;

final class ApiException extends \RuntimeException
{
    public $apiCode;
    public function __construct(string $code) { $this->apiCode = preg_match('/^[a-z_]{1,60}$/D', $code) ? $code : 'request_failed'; parent::__construct($this->apiCode); }
}
final class ApiClient
{
    private $key;
    private $base = 'https://plaxswap.com';
    public function __construct(string $key)
    {
        if (!preg_match('/^pxs_(live|test)_[a-z0-9]{10}_[A-Za-z0-9_-]{32,64}$/D', $key, $match)) { throw new ApiException('invalid_api_key'); }
        $this->key = $key;
        // This is a developer-only constant, never an editable admin URL. Live keys are always pinned to production.
        $this->base = self::origin($match[1] === 'live');
    }
    public static function origin(bool $live): string
    {
        if (!$live && defined('PLAXSWAP_WC_SANDBOX_URL') && in_array(wp_get_environment_type(), ['local','development'], true)) {
            $base = rtrim((string) PLAXSWAP_WC_SANDBOX_URL, '/');
            if (!preg_match('#^http://127\.0\.0\.1:([0-9]{2,5})$#D', $base, $port) || (int) $port[1] < 1 || (int) $port[1] > 65535) { throw new ApiException('invalid_sandbox_origin'); }
            return $base;
        }
        return 'https://plaxswap.com';
    }
    public function base(): string { return $this->base; }
    public function isLive(): bool { return strpos($this->key, 'pxs_live_') === 0; }
    public function request(string $method, string $path, ?array $body = null, string $idempotency = ''): array
    {
        if (!in_array($method, ['GET','POST'], true) || !preg_match('#^/api/v1/(?:merchant|payments(?:/pay_[a-z0-9]{16})?)$#D', $path)) { throw new ApiException('invalid_path'); }
        $headers = ['Authorization'=>'Bearer ' . $this->key, 'Accept'=>'application/json', 'User-Agent'=>'Plaxswap-WooCommerce/' . PLAXSWAP_WC_VERSION];
        if ($idempotency !== '') { $headers['Idempotency-Key'] = $idempotency; }
        $args = ['method'=>$method,'headers'=>$headers,'timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>1048577];
        if ($body !== null) { $args['body'] = wp_json_encode($body); $args['headers']['Content-Type'] = 'application/json'; }
        $response = wp_remote_request($this->base . $path, $args);
        if (is_wp_error($response)) { throw new ApiException('connection_unavailable'); }
        $status = (int) wp_remote_retrieve_response_code($response); $raw = wp_remote_retrieve_body($response);
        if (strlen($raw) > 1048576) { throw new ApiException('response_too_large'); }
        $data = json_decode($raw, true);
        if (!is_array($data)) { throw new ApiException('invalid_response'); }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- ApiException restricts remote codes to a fixed lowercase identifier pattern; this is not HTML output.
        if ($status < 200 || $status >= 300) { throw new ApiException((string) ($data['error']['code'] ?? 'request_failed')); }
        return $data;
    }
    public function merchant(): array
    {
        $merchant = $this->request('GET', '/api/v1/merchant');
        if (!preg_match('/^mer_(?:[a-z0-9]{12}|[a-z0-9]{16})$/D', $merchant['id'] ?? '') || ($merchant['status'] ?? '') !== 'active'
            || empty($merchant['custody']) || empty($merchant['onboardingComplete']) || ($merchant['mode'] ?? '') !== ($this->isLive() ? 'live' : 'test')) {
            throw new ApiException('merchant_not_ready');
        }
        return $merchant;
    }
}

