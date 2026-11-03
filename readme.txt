=== Ordervox – Order Notifications via WhatsApp for WooCommerce ===
Contributors: webbcodestudios
Tags: woocommerce, whatsapp, order notifications, notifications, customer messages
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce order, payment, stock and review alerts on WhatsApp, plus opt-in customer updates, through the official WhatsApp Cloud API.

== Description ==

Ordervox sends your WooCommerce store's important moments straight to WhatsApp, using Meta's **official WhatsApp Cloud API**. No browser automation, no unofficial gateways, no risk of your number being banned.

= Store alerts =

Switch each alert on or off:

* New order (classic checkout, block checkout and API orders)
* Payment received
* Order processing, completed or cancelled
* Full and partial refunds, with amount and reason
* Failed payments
* Low stock and out of stock (at most once a day per product)
* New product reviews
* A daily summary at the time you choose: orders, revenue, average order, top product, orders to fulfil and products out of stock

= Customer messages =

* An opt-in checkbox on the classic and block checkout (off by default, never pre-ticked), with consent recorded on each order
* Order confirmation and order completed messages to customers who opted in
* Customers can reply STOP at any time; it takes effect immediately, including for messages already queued. START subscribes them again.
* Customers can switch messages on or off in My Account
* An optional, separate consent for offers and reminders

= Templates you control =

WhatsApp only delivers business-initiated messages that use a template Meta has approved. Ordervox creates one template per alert in your WhatsApp Business Account with a single click, tracks approval, and lets you edit every message:

* Placeholders such as `{order_number}`, `{order_total}`, `{order_items}` and `{shipping_address}`
* A live, WhatsApp-style preview using your latest real order
* Checks for the rules that make Meta reject templates, before you submit
* Your approved version keeps sending while an edit is under review

= Built to be reliable =

* Messages send in the background, never during checkout
* Temporary failures retry automatically with back-off; nothing is sent twice
* A message log with sent, delivered, read and failed statuses, and one-click resend
* WhatsApp history on each order and a status column in the orders list
* A connection health check (token, number quality, webhook, queue) with an admin notice and email if something breaks
* Compatible with WooCommerce High-Performance Order Storage (HPOS) and the block-based Cart and Checkout
* Credentials are encrypted in your database
* Personal data export and erasure through the WordPress privacy tools

= Developer friendly =

Filters and actions for recipients, placeholders, payloads, delivery statuses, inbound messages and button replies. See the `ordervox_` hooks in the source.

WhatsApp is a trademark of Meta Platforms, Inc. WooCommerce is a trademark of Automattic Inc. Ordervox is not affiliated with or endorsed by either.

== External services ==

This plugin connects to the **WhatsApp Cloud API** provided by Meta Platforms, Inc. (graph.facebook.com). It is required for the plugin to work.

* **What is sent:** the recipient's phone number and the message content built from your templates. Depending on the template, this can include the order number, totals, items, customer name, phone and address, product names and review excerpts. Your WhatsApp Business Account ID, phone number ID and access token are sent to authenticate. Template text is sent when you create templates.
* **When:** whenever an enabled alert or customer message is triggered, when you send a test message, when you create or refresh templates, and during the daily connection health check.
* **Received:** Meta sends delivery statuses, template approval updates and incoming messages (such as STOP replies) to your site's webhook URL.

WhatsApp Business terms: https://www.whatsapp.com/legal/business-terms
WhatsApp Business messaging policy: https://business.whatsapp.com/policy
Meta privacy policy: https://www.facebook.com/privacy/policy/

== Installation ==

1. Install and activate WooCommerce.
2. Install and activate Ordervox.
3. Go to **WooCommerce → Ordervox**. The setup wizard walks you through connecting your WhatsApp Business number, setting up the webhook, adding your alert number and sending a test message.
4. Open the **Alerts** tab, click **Create templates in WhatsApp**, and switch on the alerts you want.

Your site must be on public HTTPS for Meta to deliver status updates to it.

== Frequently Asked Questions ==

= What do I need from Meta? =

A Meta Business portfolio, a Meta developer app with the WhatsApp product added, a phone number registered with the WhatsApp Cloud API, and a permanent System User access token with the `whatsapp_business_messaging` and `whatsapp_business_management` permissions. The setup wizard links to Meta's guide and tells you where to find each value.

= Can I use the number that is on my phone's WhatsApp app? =

The business number must be registered with the Cloud API, which means it cannot be in use on the WhatsApp or WhatsApp Business app at the same time. Alerts are sent from the business number to your personal number, so use a separate number for each.

= Does it cost money? =

The plugin is free. Meta charges per delivered template message, based on the message category and the recipient's country. Ordervox's store alerts and order updates are "utility" templates, the lower-priced category. See Meta's WhatsApp pricing page for current rates.

= Why do my alerts need templates? =

WhatsApp only lets businesses start a conversation with a template Meta has approved. Ordervox creates them for you; approval usually takes minutes. Until a template is approved, the message log shows why an alert was not sent.

= Can I change the wording? =

Yes. Every alert has an editor with placeholders and a live preview. Edits are sent to Meta as a new version; the previous approved version keeps working until the new one is approved.

= Is this GDPR compliant? =

Customers are only messaged after they tick an unticked opt-in box. Ordervox records when and how they consented, honours STOP immediately, and supports the WordPress personal data export and erase tools. You remain responsible for your privacy policy; Ordervox adds suggested wording under Settings → Privacy.

= Does it work with the block checkout and HPOS? =

Yes, both are supported and declared compatible.

= Which languages are supported? =

Templates can be created in any language WhatsApp supports. The default text follows your site language when a translation is available.

== Screenshots ==

1. Connection health dashboard.
2. Alerts and template approval status.
3. Template editor with placeholders and live WhatsApp preview.
4. Setup wizard: connecting your Meta app.
5. Message log with delivery and read statuses.
6. WhatsApp opt-in on the block checkout.
7. WhatsApp messages on the order screen.

== Changelog ==

= 1.0.1 =
* Refreshed admin design: colour-coded alert cards, sliding tabs, page transitions and animated statistics, with reduced-motion support.

= 1.0.0 =
* First public release: store alerts, daily summary, customer opt-in and order updates, STOP handling, template management, message log, health checks.

== Upgrade Notice ==

= 1.0.0 =
First public release.
