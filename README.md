# Plaxswap Crypto Payments for WooCommerce

Accept Bitcoin, USDT, Ether and more in WooCommerce. The order moves to Processing once the payment is verified, and the funds land in your own Plaxswap merchant wallet, in the coin the customer paid.

This repository holds the source of the plugin published on WordPress.org as [plaxswap-crypto-payments](https://wordpress.org/plugins/plaxswap-crypto-payments/), version 1.0.2.

## Install

In WordPress, open Plugins, then Add New Plugin, and search for "Plaxswap Crypto Payments". You can also download the ZIP from the [WordPress.org page](https://wordpress.org/plugins/plaxswap-crypto-payments/).

Requirements: WordPress 6.8 or newer, WooCommerce 9.0 or newer, PHP 7.4 or newer with OpenSSL, and a Plaxswap merchant account.

## Set up

1. Create a test API key and webhook signing secret in your [merchant dashboard](https://plaxswap.com/merchants/dashboard) under Developers.
2. Open WooCommerce, Settings, Payments, Plaxswap, and paste both.
3. Add the callback URL the plugin shows to Developers, Webhooks in the dashboard.
4. Place a test order before you switch to a live key.

Full steps with screenshots: [plaxswap.com/integrations/woocommerce](https://plaxswap.com/integrations/woocommerce) and [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Fees and support

Fees: [plaxswap.com/fees](https://plaxswap.com/fees). Support: [plaxswap.com/support](https://plaxswap.com/support). Never send an API key, signing secret, recovery phrase or authenticator code to anyone, including support.

## License

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).
