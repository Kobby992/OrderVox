=== Ordervox – Order Notifications via WhatsApp for WooCommerce ===
Contributors: webbcodestudios
Tags: woocommerce, whatsapp, order notifications, notifications, cloud api
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Get WooCommerce order, payment, stock and review alerts on WhatsApp through the official WhatsApp Cloud API.

== Description ==

Ordervox sends WooCommerce events to your WhatsApp number using Meta's official WhatsApp Cloud API. No browser automation and no risk of number bans.

* New order, payment received, order status changes, failed payments, low stock and new review alerts.
* Switch each alert on or off.
* Editable message templates with placeholders.
* Background sending with retries and a full delivery log.
* Compatible with WooCommerce High-Performance Order Storage (HPOS) and the block-based Cart and Checkout.

WhatsApp is a trademark of Meta Platforms, Inc. WooCommerce is a trademark of Automattic Inc. Ordervox is not affiliated with or endorsed by either.

== External services ==

This plugin connects to the **WhatsApp Cloud API** provided by Meta Platforms, Inc. (graph.facebook.com) to send WhatsApp messages and manage message templates for your WhatsApp Business Account.

* What is sent: the recipient phone number and the message content built from your templates (for example order number, totals, customer name and address, product names), plus your WhatsApp Business Account and phone number IDs and access token.
* When: whenever an enabled event occurs (for example a new order), when you send a test message, and when you sync templates or check your connection.
* Meta also sends delivery and incoming-message notifications to your site's webhook URL.

Meta terms: https://www.whatsapp.com/legal/business-terms
Meta privacy policy: https://www.whatsapp.com/legal/privacy-policy

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate Ordervox.
3. Go to WooCommerce → Ordervox and follow the setup steps to connect your WhatsApp Business number.

== Frequently Asked Questions ==

= Do I need a WhatsApp Business account? =

Yes. You need a Meta Business portfolio, a Meta developer app with the WhatsApp product, and a phone number registered with the WhatsApp Cloud API.

= Does sending messages cost money? =

Meta charges per delivered template message, based on the message category and the recipient's country. See Meta's WhatsApp pricing page for current rates.

== Changelog ==

= 0.1.0 =
* Initial development release.
