=== Plaxswap Crypto Payments for WooCommerce ===
Contributors: plaxswap
Tags: crypto payments, bitcoin, usdt, payment gateway, woocommerce
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin, USDT, Ether and more in WooCommerce. Orders update automatically and funds land in your own Plaxswap wallet.

== Description ==

**Make your store crypto-ready in minutes.** Customers pay with **Bitcoin, USDT, Ether, USDC, Solana, Litecoin** and more. The order moves to Processing automatically once the payment is verified, and every payment lands in **your own Plaxswap merchant wallet**, in the coin the customer paid. No exchange account, no card chargebacks, no extra page to build.

Setup guide with screenshots: [plaxswap.com/integrations/woocommerce](https://plaxswap.com/integrations/woocommerce).

= Why store owners choose it =

* ⚡ **Built for WooCommerce.** Works with the Checkout Block and the classic checkout, on desktop and phone. HPOS compatible.
* 🧾 **A checkout customers understand.** They pick a coin and network, see the exact amount, address and QR code, and follow every confirmation live.
* ✅ **Orders you can trust.** A signed webhook *and* an independent API check confirm the payment before the order is marked paid. A scheduled check catches anything delayed. A customer returning to your store is never treated as proof of payment.
* 💼 **Your own merchant wallet.** 133 coins on 17 networks. Receive, send, swap and withdraw your balance whenever you want.
* 🛡 **Security inspired by the big exchanges.** Authenticator or passkey on every send, an address book with a 24-hour wait, and automatic pauses after security changes.
* 🧪 **Test mode.** Store administrators and shop managers can place test orders with no real funds before going live.

= New in 1.0.2: a new merchant dashboard =

* A cleaner dashboard: Overview, Wallet, Payments, Swap, Activity, Developers and Settings.
* The merchant wallet holds **133 coins on 17 networks**. Each coin has its own page with Receive, Send and Swap for every network.
* Press Ctrl+K (Cmd+K on a Mac) to search payments, orders, coins and saved addresses.
* API keys and Webhooks now sit under **Developers** in the dashboard.

= The Plaxswap merchant wallet =

* **Receive** coins to your own deposit address per coin and network, with a QR code. Receiving is free.
* **Send** to addresses saved in your address book. A new address can be used 24 hours after you save it, so a stolen login cannot empty your wallet in a hurry.
* **Swap** between coins inside your wallet, with the rate and every fee shown before you approve.
* **Simple wallet fees:** 0.3% on the same network · 0.5% for the same coin on another network · 0.7% for a different coin, plus the network fee.
* **Security that works for you:** anti-phishing code in every Plaxswap email, a list of signed-in devices, a "Lock my account" link in security emails, and sends paused for 24 hours after a security change or a new-device sign-in. Payments keep arriving while sends are paused.

= How it works =

1. Your customer chooses **Crypto · Plaxswap** at checkout and places the order.
2. Plaxswap checkout shows the coin, network, exact amount, address and QR code.
3. The customer pays from any wallet or exchange and follows the confirmations live.
4. The payment is verified, the order moves to Processing, and the funds appear in your merchant wallet.

= Fees =

* **Checkout:** the standard platform fee is 1.5% (or the fee set for your merchant account). The checkout shows the customer every charge before they pay. The plugin adds no extra surcharge.
* **Your wallet:** receiving is free. Sends and swaps cost 0.3%, 0.5% or 0.7% as above, plus the network fee. The preview shows what arrives and what is deducted before you confirm.

Current fees for every coin and network: [plaxswap.com/fees](https://plaxswap.com/fees).

= Orders, history and tracking =

* WooCommerce order screen: Plaxswap payment status, coin and network, credited amount, payment reference, and a **Refresh Plaxswap payment** order action.
* Merchant dashboard: income, orders and wallet activity with date, coin, network, status and reference filters.
* A unique payment and tracking link for each order.

Coin and network availability, minimum amounts and fees come from Plaxswap checkout. They are not fixed in the plugin.

== Requirements ==

* WordPress 6.8 or newer and WooCommerce 9.0 or newer, in a version combination supported by WooCommerce.
* PHP 7.4 or newer with OpenSSL.
* A Plaxswap merchant account with an integration API key and webhook signing secret.
* HTTPS on your public store, outbound HTTPS access to plaxswap.com, and a publicly reachable payment callback.
* Working WordPress scheduled tasks for payment reconciliation.

Local testing is supported with isolated simulated payments. Public Plaxswap webhooks cannot reach a private localhost store without a separately configured, secure test route.

== Installation ==

1. In WordPress, open Plugins → Add New Plugin → Upload Plugin.
2. Choose the release ZIP, install it, then activate Plaxswap Crypto Payments for WooCommerce.
3. Open WooCommerce → Settings → Payments → Plaxswap.
4. Use the setup guide to open [plaxswap.com/merchants/dashboard](https://plaxswap.com/merchants/dashboard) and create a test integration API key.
5. Save the key and its matching webhook signing secret in the plugin settings. Leave test mode enabled.
6. Copy the callback URL shown by the plugin. In your merchant dashboard, open Developers → Webhooks, add that exact URL and enable payment events.
7. Choose Save changes. Saving checks the merchant connection before updating your settings.
8. Place a test order while signed in as a store administrator or shop manager. Verify checkout, the paid order status and payment details before switching to live credentials.

For the full guide, see docs/INSTALLATION.md included in the plugin package.

== Frequently Asked Questions ==

= Where do I get an API key? =

Sign in at [plaxswap.com/merchants/dashboard](https://plaxswap.com/merchants/dashboard). Complete your business and authenticator setup, then open Developers → API keys. Create a test integration key for testing or a live integration key for customer payments. Use the matching webhook signing secret from the same merchant account.

= What is a webhook? =

A webhook is a payment notification Plaxswap sends to your store. The plugin shows your store's callback URL. Add it under Developers → Webhooks in your merchant dashboard. The plugin checks its signature and then retrieves the authoritative payment before updating an order.

= Which coins and networks can customers use? =

Customers can select from the methods available for that specific payment at Plaxswap checkout. Availability depends on the current gateway configuration, network readiness, amount and applicable service rules. The plugin does not maintain a separate coin list.

= Where do merchant funds go? =

Settled payments credit your Plaxswap merchant wallet in the original coin and network paid. The wallet holds 133 coins on 17 networks. Open it at [plaxswap.com/merchants/dashboard](https://plaxswap.com/merchants/dashboard) to receive, send to your saved addresses, swap between coins or withdraw. Wallet fees are 0.3% on the same network, 0.5% for the same coin on another network and 0.7% for a different coin, plus the network fee; every fee is shown before you approve.

= Does returning to the store mark an order paid? =

No. The plugin only marks an order paid after a signed notification or reconciliation triggers an independent Plaxswap API verification of the matching payment. A pending, incomplete or unverified payment remains unpaid.

= What happens if a payment notification is delayed? =

The plugin also schedules status checks for unsettled payments, normally about every five minutes while your WordPress scheduled tasks are running. Automatic retry scheduling stops 30 days after the payment was created, or earlier once it is paid. Store managers can use Refresh Plaxswap payment in the order actions to request an API check. Signed webhooks and manual checks still verify the authoritative payment; the time limit is not a guarantee of settlement or a refund deadline.

= Does this plugin support automatic renewals or refunds? =

This version supports individual checkout payments. Automatic recurring billing and automatic WooCommerce refunds are not supported. Arrange any customer refund separately through your merchant-controlled payment process or Plaxswap support. Changing a WooCommerce order to Refunded does not send crypto to the customer.

= What happens if I delete the plugin? =

Deleting or deactivating this plugin retains its WordPress settings, encrypted integration credentials and order payment history. Reinstalling can reuse those records with the same WordPress security salts. Deletion does not close your Plaxswap merchant account or delete remote payment records. Revoke an integration key in your merchant dashboard if you no longer want this store to have access.

= Does the plugin store a wallet recovery phrase? =

No. The plugin uses a merchant integration key and webhook signing secret. It does not request or store a hot-wallet key, wallet recovery phrase or merchant authenticator secret, and it does not initiate merchant withdrawals.

= How are fees shown? =

Plaxswap checkout presents the quote and applicable platform, exchange and network charges before payment. The plugin does not silently add another store-side crypto surcharge.

The order details label Platform fee · invoice basis shows the API's fee amount calculated from the invoice total. Final checkout charges and network or exchange costs are separate; this invoice-based figure is not a report of realized platform profit.

= How do I get support? =

Visit [plaxswap.com/support](https://plaxswap.com/support) for 24/7 human support. Include your plugin version, WooCommerce version, public payment reference and a description of the issue. Never send an API key, signing secret, wallet recovery phrase or authenticator code.

== External Service and Privacy ==

This plugin connects to Plaxswap at [plaxswap.com](https://plaxswap.com) to create and verify crypto payments. It transmits the order amount, currency, an opaque order reference, verification metadata, a description, and store return/cancel URLs. A customer billing email is sent only when the store enables the optional email-sharing setting.

The hosted checkout receives the customer's own web request. The plugin's API integration credentials are encrypted on the WordPress server using its security salts; they are not included in the customer's checkout page. Preserve the WordPress salts when restoring a backup, or re-enter the credentials afterward. Order payment references and a limited payment summary are stored in WooCommerce for reconciliation.

Plaxswap is operated by SAMZAL LLC. Review its [terms](https://plaxswap.com/legal/terms) and [privacy policy](https://plaxswap.com/legal/privacy). Service availability information is at [plaxswap.com/compliance](https://plaxswap.com/compliance).

== Screenshots ==

1. Get paid in crypto on your WooCommerce store: every payment lands in your own merchant wallet (sample figures).
2. How it works: from checkout to a paid, verified order in one flow.
3. Guided setup in WooCommerce > Settings > Payments.
4. Crypto · Plaxswap in the WooCommerce checkout (Checkout Block and classic checkout).
5. The Plaxswap checkout: search a coin, pick the network, see the exact amount.
6. Verified order with Plaxswap payment details and the Refresh Plaxswap payment action.
7. Merchant wallet: orders, balance and wallet fees.
8. Send and withdraw to your saved addresses, with a full fee preview.
9. Swap between coins inside your wallet.
10. Receive coins to your own deposit address with a QR code.
11. Address book with a 24-hour wait on new addresses.
12. Merchant account security: authenticator, passkeys, anti-phishing code and devices.

== Changelog ==

= 1.0.2 =

* Setup texts follow the new Plaxswap merchant dashboard: API keys and Webhooks are under Developers.
* The description covers the new merchant wallet: 133 coins on 17 networks, a page per coin, an Activity page and search.
* No change to checkout, payment verification or stored data. Update in place.

= 1.0.1 =

* The settings page and installation guide now describe the Plaxswap merchant wallet: receive with a QR code, send to saved addresses, swap between coins, and the 0.3% / 0.5% / 0.7% wallet fees.
* New plugin description, banner and screenshots.
* No change to checkout, payment verification or stored data. Update in place.

= 1.0.0 =

* Initial WooCommerce gateway with guided merchant setup, hosted crypto checkout, signed payment notifications, verified order updates, test mode and Checkout Block integration.

== Upgrade Notice ==

= 1.0.2 =

Text update for the new Plaxswap merchant dashboard. No change to checkout or payment verification.

= 1.0.1 =

Text and listing update for the new Plaxswap merchant wallet. No change to checkout or payment verification.

= 1.0.0 =

First release. Configure test credentials and complete a test order before enabling live payments.
