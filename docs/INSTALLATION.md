# Plaxswap for WooCommerce

Accept crypto at checkout while continuing to manage products, customers and orders in WooCommerce.

## Before you begin

You need WordPress 6.8 or newer, WooCommerce 9.0 or newer and PHP 7.4 or newer with OpenSSL. Choose a WordPress/WooCommerce version combination supported by WooCommerce. You also need a Plaxswap merchant account. Sign in at [the merchant dashboard](https://plaxswap.com/merchants/dashboard), finish the business setup and enable your authenticator. Your store should use public HTTPS before real customer payments are enabled.

Keep your merchant integration key and webhook signing secret private. Do not enter a wallet recovery phrase or your authenticator setup key anywhere in this plugin.

## Install the plugin

1. Open **WordPress → Plugins → Add New Plugin → Upload Plugin**.
2. Select the Plaxswap release ZIP, choose **Install Now**, then **Activate Plugin**.
3. Open **WooCommerce → Settings → Payments → Plaxswap**.

The checkout payment option works with both the classic WooCommerce checkout and the Checkout Block. Use the block editor to ensure your checkout page contains WooCommerce's Checkout Block if that is the layout your store uses.

## Connect your merchant account

1. Open **Developers → API keys** in your [merchant dashboard](https://plaxswap.com/merchants/dashboard).
2. Create an integration key in **test mode**. Test keys begin with `pxs_test_`; live keys begin with `pxs_live_`.
3. Paste the test key into the plugin's **API key** field.
4. Paste the matching **webhook signing secret** into its separate field. This is not the API key.
5. Leave **Test mode** enabled and save the settings.

The selected mode and key must match. Test checkout is visible only to signed-in store administrators and shop managers, and must not be presented as a real customer payment method.

Saved API keys and signing secrets are encrypted and are not filled back into the settings form. Leave a credential field blank to keep the saved value. To replace a credential, paste the complete new value into its field and save. Back up your WordPress security salts with the site; restoring with different salts requires entering the credentials again.

## Connect payment notifications

The callback URL is the address where your store receives payment updates. Your plugin settings show the exact URL for this installation. Copy it directly instead of typing or guessing a URL.

1. In the plugin settings, copy **Callback URL**.
2. In your merchant dashboard, open **Developers → Webhooks**.
3. Add the copied URL and enable the payment events you want the store to receive.
4. Confirm the webhook uses the matching signing secret configured in WordPress.
5. Choose **Save changes** in WooCommerce settings. Saving verifies the merchant connection before the settings are updated.

The URL tells Plaxswap where to deliver an update. The signing secret lets the plugin verify that the update came from Plaxswap. After that verification, the plugin checks the payment through the merchant API before changing your order.

A public hosted service cannot deliver a webhook directly to `127.0.0.1` or another private localhost address. The local test environment uses isolated simulated delivery. Public store callbacks require a publicly reachable HTTPS endpoint.

## Place your first test order

1. Sign in to WordPress as a store administrator or shop manager.
2. Add a low-priced test product to the cart and go to checkout.
3. Select **Pay with crypto** and place the order.
4. Review the payment page and complete its supported test-payment flow. Do not send real coins to an isolated simulator.
5. Check that a pending payment is not treated as paid.
6. After simulated settlement, verify that the order updates once, the payment reference is saved, and payment details match the order total and currency.

Repeat the test for the classic checkout and Checkout Block if your store uses both. Also test a failed or expired payment and verify that it does not fulfil the order.

## Enable customer payments

After test payments pass, create a **live integration key** from the same merchant account, replace the test key with it, use its matching signing secret and turn off **Test mode**. Choose **Save changes** to verify and save the new connection. Your public checkout must use HTTPS.

Customers select their coin and network at Plaxswap checkout. The available methods and minimum amounts come from the gateway for that payment, rather than a list hardcoded in WordPress. Fees are shown in the quote before the customer sends funds.

In the WooCommerce order details, **Platform fee · invoice basis** shows the API's fee amount calculated from the invoice total. Final checkout charges and network or exchange costs are separate. This invoice-based amount does not represent realized platform profit.

## Payment status and delayed updates

The Plaxswap merchant API is the authority for payment status. A signed webhook or a status-refresh action tells the plugin to check the matching payment. Before an order is marked paid, the plugin verifies its payment ID, live/test mode, original amount and currency, order references and final merchant credit. Neither the browser return page nor a notification's claimed status is sufficient on its own.

For unsettled payments, the plugin schedules another status check about every five minutes while WordPress scheduled tasks are running. Automatic retry scheduling stops 30 days after the payment was created, or earlier when it is paid. This bound prevents abandoned payments from creating endless background jobs; it does not define a refund deadline. Signed webhook delivery and a store manager's manual **Refresh Plaxswap payment** action can still trigger an authoritative check.

If a payment arrives late or after an order has been cancelled, failed or refunded, review the payment and order before fulfilment. Such a payment must not automatically fulfil an otherwise closed order.

## Your merchant wallet

Settled funds credit your merchant wallet in the original coin and network paid. Open **Wallet** in your [merchant dashboard](https://plaxswap.com/merchants/dashboard). It holds 133 coins on 17 networks, each with its own page. Use it to review balances, orders and history (the **Activity** page lists every payment, send and swap), and to:

- **Receive** coins to your own deposit address per coin and network, with a QR code. Receiving is free.
- **Send** to addresses saved in your address book. A new address can be used 24 hours after you save it (one new address per 24 hours).
- **Swap** between coins inside the wallet, with the rate and every fee shown before you approve.

Wallet fees on sends and swaps: 0.3% on the same network, 0.5% for the same coin on another network, 0.7% for a different coin, plus the network fee. Every send needs your authenticator code or a passkey, and sends pause for 24 hours after a security change or a sign-in from a new device. The WooCommerce plugin does not initiate sends or hold a wallet recovery phrase.

## Refunds and renewals

This release supports individual checkout payments. Automatic recurring billing and automatic WooCommerce refund transfers are not supported. Arrange any customer refund separately through your merchant-controlled payment process or [Plaxswap support](https://plaxswap.com/support). Marking an order **Refunded** in WooCommerce records its store status; it does not send coins to the customer.

## Deactivation, deletion and backups

Deactivating or deleting the plugin retains its WordPress settings, encrypted integration credentials and payment history attached to WooCommerce orders. It does not close the merchant account or delete payment records held by Plaxswap. Back up the store before changing or removing payment records.

If you reinstall or restore the store, keep the original WordPress security salts to reuse the encrypted credentials. If those salts change, enter the credentials again. Revoke the store's integration key from **API keys** in your merchant dashboard if the store should no longer have merchant API access.

## Troubleshooting

| What you see | What to check |
| --- | --- |
| Plaxswap is missing from checkout | Activate WooCommerce and this plugin. Enable the gateway, save valid credentials, and check that test mode is being viewed by a signed-in store administrator or shop manager. |
| The API key is rejected | Copy the full integration key. Confirm `pxs_test_` matches test mode or `pxs_live_` matches live mode. |
| A payment remains pending | Check the payment's actual confirmation status, callback delivery and signing secret. Refresh its status from the order's Plaxswap payment details. |
| The customer returned but the order is unpaid | Returning to the store does not prove payment. The verified gateway payment must be settled first. |
| Saving the connection fails | Verify outbound HTTPS, the merchant key, its permissions and the merchant account status. Do not disable certificate validation. |
| Webhooks cannot reach the store | Confirm that the exact callback URL is public HTTPS and is not blocked by maintenance mode, login protection or a firewall. |
| One payment produces repeated notifications | Repeated delivery is normal for webhooks. Verified paid updates must not fulfil or reduce stock more than once. |

For help, contact [Plaxswap support](https://plaxswap.com/support). Send the public payment reference and software versions; keep private credentials out of messages and screenshots.

## Data sent to Plaxswap

The plugin sends the order amount and currency, an opaque order reference, verification metadata, a description, and return/cancel URLs. Sharing the customer's billing email is optional and off by default. Review your store's own privacy notice before enabling it.

The plugin stores payment references and a limited payment summary on your WordPress server. API keys and webhook secrets are encrypted with authenticated encryption tied to your WordPress security salts, and are not displayed to checkout visitors. It never requests a hot-wallet key, recovery phrase or merchant authenticator secret.

[Merchant API documentation](https://plaxswap.com/developers/payments) · [Privacy policy](https://plaxswap.com/legal/privacy) · [Terms](https://plaxswap.com/legal/terms)
