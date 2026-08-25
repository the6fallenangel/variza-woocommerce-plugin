=== Variza for WooCommerce ===
Contributors: the6fallenangels
Tags: payment gateway, woocommerce, card to card, iran, bank transfer
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Card-to-card (bank transfer) payment gateway for WooCommerce with fully automatic order verification — no manual receipt checking.

== Description ==

Many WooCommerce stores in Iran still rely on direct card-to-card bank transfers: the buyer transfers the amount, sends a receipt, and the store owner has to manually match the receipt to the order and update its status. This is slow and error-prone.

**Variza** removes that manual step entirely. Once this plugin is installed:

1. The customer selects "Variza" at checkout and is redirected to the Variza payment page.
2. The customer transfers the exact order amount directly to your bank account, card to card.
3. The Variza service reads the resulting bank SMS notification on a connected phone and matches the amount and details to the order.
4. Within seconds, with no human involvement, the order is automatically marked **"Paid"** in WooCommerce and the customer is returned to your site.

This plugin is the official [Variza](https://variza.ir) integration for WooCommerce and supports both the classic checkout and the Cart & Checkout Blocks experience.

= Features =

* Fully automatic payment confirmation — no receipt review, no contacting the customer; the order is marked paid automatically once a signed webhook is received from Variza.
* Compatible with both WooCommerce Blocks (Cart & Checkout Blocks) and the classic checkout.
* The order-received page automatically polls for status updates, so it refreshes as soon as payment is confirmed, without a manual page reload.
* Supports both Toman and Rial pricing; the plugin automatically converts the amount for Variza, which only accepts Toman.
* Every webhook is verified with an HMAC-SHA256 signature; no forged request can mark an order as paid.
* Payment-link creation, errors, and final confirmation are all logged to the order notes and to the WooCommerce logs.
* A dedicated settings screen with quick setup, connection status, and a step-by-step guide.

A Variza account (https://variza.ir) is required to use this plugin.

== External services ==

This plugin connects to the Variza card-to-card payment service (https://variza.ir), which is required for the plugin to function, since it is what generates payment links and confirms that a bank transfer was received.

* **Creating a payment link**: when a customer chooses Variza at checkout, the plugin sends the order amount, a return URL, and an order title to `https://variza.ir/api/v1/pay` using your Variza API key. Variza responds with a payment page URL that the customer is redirected to.
* **Receiving payment confirmation**: after the customer transfers the money, Variza sends a signed webhook (HMAC-SHA256) back to this site's REST endpoint (`/wp-json/variza/v1/webhook`) confirming the payment, including the amount and a tracking code. No card or bank-account details ever pass through your server.
* This happens on every checkout that uses Variza as the payment method, and again once per payment confirmation.

Variza's Terms of Service: https://variza.ir/terms-and-conditions
Variza's Privacy Policy: https://variza.ir/privacy-policy
Variza's Disclaimer: https://variza.ir/disclaimer

== Installation ==

1. Install and activate the plugin from Plugins → Add New → Upload Plugin in your WordPress dashboard.
2. Create an account at [variza.ir](https://variza.ir) and copy your API key and webhook signing key from the Variza dashboard.
3. From the "Variza" menu in your WordPress dashboard, enter and save those keys.
4. Copy the webhook URL shown on that same settings page and register it in the Variza dashboard (Profile → Webhook).
5. Enable the gateway.

== Frequently Asked Questions ==

= Does my customers' card information pass through my site? =

No. The customer is redirected to Variza's secure payment page and transfers the amount directly to your bank account; no card data ever passes through your store's server.

= What happens if my connected phone is off or offline? =

The payment link is still generated for the customer, but automatic confirmation won't happen until the bank SMS reaches Variza. If the phone is offline, the Android app waits for a connection; if the phone is powered off, you'll need to confirm the order manually from the WooCommerce dashboard.

= My store prices are in Rial, is that a problem? =

No; set the currency unit to "Rial" in the plugin settings and the amount will automatically be divided by 10 before being sent to Variza (which only accepts Toman).

More answers at [variza.ir/faq](https://variza.ir/faq).

== Changelog ==

= 1.0.1 =
* Compliance fixes from WordPress.org plugin review: English readme, documented external service usage, removed remote font loading, properly enqueued admin CSS/JS, removed load_plugin_textdomain(), added Requires Plugins header.

= 1.0.0 =
* Initial release: payment link creation, automatic confirmation via signed webhook, classic and blocks checkout support, dedicated settings page.