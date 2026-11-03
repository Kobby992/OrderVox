# Ordervox — Build Specification

> WhatsApp order notifications, two-way order management and customer messaging for WooCommerce, powered by the official WhatsApp Cloud API (Meta).

| | |
|---|---|
| **Product** | Ordervox (Free) + Ordervox Pro (paid add-on) |
| **Developer** | Webbcode Studios |
| **Distribution** | Free plugin on WordPress.org, Pro sold separately (Freemius) |
| **Market** | Global |
| **Spec version** | 1.0 — 2026-11-03 |

---

## Table of contents

1. [Product summary](#1-product-summary)
2. [Brand and identifiers](#2-brand-and-identifiers)
3. [Free vs Pro feature matrix](#3-free-vs-pro-feature-matrix)
4. [Technical requirements](#4-technical-requirements)
5. [Architecture](#5-architecture)
6. [WhatsApp Cloud API integration](#6-whatsapp-cloud-api-integration)
7. [Events and triggers](#7-events-and-triggers)
8. [Message templates and placeholders](#8-message-templates-and-placeholders)
9. [Feature specifications](#9-feature-specifications)
10. [Data model](#10-data-model)
11. [Admin UI](#11-admin-ui)
12. [REST API](#12-rest-api)
13. [Security](#13-security)
14. [Privacy and compliance](#14-privacy-and-compliance)
15. [Internationalisation](#15-internationalisation)
16. [Performance](#16-performance)
17. [Developer extensibility](#17-developer-extensibility)
18. [Testing and quality](#18-testing-and-quality)
19. [WordPress.org submission rules](#19-wordpressorg-submission-rules)
20. [Build phases and milestones](#20-build-phases-and-milestones)
21. [Claude Code skills and plugins to use](#21-claude-code-skills-and-plugins-to-use)
22. [Store-owner setup guide (Meta side)](#22-store-owner-setup-guide-meta-side)
23. [Open questions](#23-open-questions)

---

## 1. Product summary

Ordervox sends WooCommerce events (new orders, payments, status changes, failed payments, low stock, new reviews) to a store owner's WhatsApp number through the **official WhatsApp Cloud API**. Each event can be switched on or off, and each message uses an editable template with placeholders.

The Pro add-on turns WhatsApp into a full order-management and customer-messaging channel: team routing, tap-to-act order buttons, cash-on-delivery (COD) confirmation, abandoned-cart recovery, PDF invoices, AI order insights (Claude), sales digests, multi-vendor support and analytics.

**Why buyers will choose Ordervox**

- Official Meta API only: no number bans, no browser automation.
- Modern WooCommerce support from day one: HPOS order storage and block-based Cart/Checkout.
- Reliable delivery: background queue, retries, full delivery/read log.
- Two-way: act on orders from WhatsApp, not just read about them.
- AI-assisted: order summaries, fraud/risk flags, suggested replies.

---

## 2. Brand and identifiers

The brand is **Ordervox** ("vox" = voice: orders that speak to you). It was chosen over the working name "WooWhats" because WooCommerce's trademark guidelines discourage product names starting with "Woo" (and WordPress.org reviewers reject `woo…` slugs), and Meta's brand guidelines restrict names that imitate "WhatsApp".

**Trademark rules to keep following**

- "WooCommerce" and "WhatsApp" appear only descriptively, after the brand: "… via WhatsApp for WooCommerce". Never in the logo, slug or domain.
- No WhatsApp logo, green chat-bubble lookalike or Meta branding in the plugin icon, banner or marketing.
- Note in readme and site footer: "WhatsApp is a trademark of Meta Platforms, Inc. WooCommerce is a trademark of Automattic Inc. Ordervox is not affiliated with or endorsed by either."

**Availability (checked 2026-11-03)**

| Check | Result | Action |
|---|---|---|
| WordPress.org slug `ordervox` | Not in use | Reserve by submitting the free plugin; a slug is fixed once approved |
| ordervox.com / .io / .app | No DNS records (may still be registered or parked) | Confirm and register at a registrar |
| Trademarks | Not checked | Search USPTO, EUIPO, WIPO Global Brand Database and your home registry before launch; consider filing in classes 9 and 42 |
| Social handles | Not checked | Claim @ordervox on X, Instagram, LinkedIn, YouTube, GitHub |

**Identifiers** (all code uses these; the WordPress.org slug can never be changed after approval):

| Identifier | Value |
|---|---|
| Display name | Ordervox – Order Notifications via WhatsApp for WooCommerce |
| Slug / folder | `ordervox` |
| Main file | `ordervox.php` |
| Text domain | `ordervox` (must equal the slug) |
| PHP namespace | `Webbcode\Ordervox` |
| Function/option/meta prefix | `ordervox_` / `_ordervox_` |
| Constant prefix | `ORDERVOX_` |
| DB table prefix | `{$wpdb->prefix}ordervox_` |
| REST namespace | `ordervox/v1` |
| Pro add-on slug | `ordervox-pro` (namespace `Webbcode\OrdervoxPro`) |
| Author | Webbcode Studios |

---

## 3. Free vs Pro feature matrix

WordPress.org forbids locked or trial code inside the free plugin (see §19). **Pro is a separate plugin** that requires the free one and plugs in through hooks.

| Feature | Free | Pro |
|---|:-:|:-:|
| **Connection** | | |
| Official WhatsApp Cloud API (manual credentials) | ✅ | ✅ |
| Guided setup wizard + "Send test message" | ✅ | ✅ |
| Connection health check (token validity, number quality rating, messaging tier) | ✅ | ✅ |
| Meta Embedded Signup (one-click connect) | — | ✅ (requires Webbcode to be a Meta Tech Provider; see §23) |
| **Recipients** | | |
| One store-owner number | ✅ | ✅ |
| Team: unlimited staff numbers, roles, quiet hours per member | — | ✅ |
| Smart routing (by category, product, shipping zone, payment method, order value, vendor) | — | ✅ |
| **Admin events (each toggleable)** | | |
| New order | ✅ | ✅ |
| Payment received | ✅ | ✅ |
| Status: processing / completed / cancelled / refunded | ✅ | ✅ |
| Failed payment | ✅ | ✅ |
| Low stock / out of stock | ✅ | ✅ |
| New product review | ✅ | ✅ |
| Custom order statuses | — | ✅ |
| **Templates** | | |
| Editable templates with placeholders + live preview | ✅ | ✅ |
| Sync/create templates with Meta from the plugin | ✅ | ✅ |
| Multi-language template variants | ✅ (1 language) | ✅ (unlimited) |
| Conditional blocks (`{if:coupon}…{/if}`) & custom fields / order meta placeholders | — | ✅ |
| **Customer messaging** | | |
| WhatsApp opt-in at checkout (classic + block checkout) | ✅ | ✅ |
| Customer order confirmation + status updates | ✅ (confirmation + completed) | ✅ (all statuses + tracking links) |
| PDF invoice attached to message | — | ✅ |
| COD order confirmation with Yes/No buttons | — | ✅ |
| Abandoned-cart recovery sequence | — | ✅ |
| Review request after delivery | — | ✅ |
| **Two-way** | | |
| Order action buttons in admin alerts (Mark shipped / Cancel / Call customer) | — | ✅ |
| Text commands (`status 1234`, `ship 1234`, `today`) | — | ✅ |
| WhatsApp inbox inside wp-admin (view & reply to customer chats) | — | ✅ |
| **AI (Claude)** | | |
| AI order summary in admin alert | — | ✅ |
| Risk / fraud flag on new orders | — | ✅ |
| AI suggested replies in inbox | — | ✅ |
| AI template writer / translator | — | ✅ |
| **Reporting** | | |
| Message log (sent / delivered / read / failed) with retry | ✅ (30-day retention) | ✅ (configurable retention + CSV export) |
| Daily sales summary to owner | ✅ | ✅ |
| Weekly/monthly digests, custom schedule | — | ✅ |
| Analytics dashboard (read rate, recovered revenue, COD confirmation rate, cost estimate) | — | ✅ |
| **Integrations** | | |
| Multi-vendor: Dokan, WCFM, MultiVendorX (vendor gets own alerts) | — | ✅ |
| WooCommerce Subscriptions events (renewal, failed renewal, cancelled) | — | ✅ |
| Shipment tracking plugins (Advanced Shipment Tracking, WooCommerce Shipment Tracking) | — | ✅ |
| PDF invoice plugins (PDF Invoices & Packing Slips) | — | ✅ |
| WPML / Polylang / TranslatePress language detection | ✅ | ✅ |
| Webhooks out (Zapier/Make/n8n) | — | ✅ |

---

## 4. Technical requirements

| Requirement | Minimum | Notes |
|---|---|---|
| WordPress | 6.6 | `Requires Plugins: woocommerce` header (WP 6.5+) |
| WooCommerce | 9.0 | Additional Checkout Fields API for block checkout |
| PHP | 8.1 | Typed properties, enums, readonly, `never` |
| MySQL / MariaDB | 5.7 / 10.4 | |
| HTTPS | Required | Meta webhooks only call HTTPS URLs |
| WP-Cron / Action Scheduler | Required | Bundled with WooCommerce |

**Compatibility declarations (in the main plugin file)**

```php
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ORDERVOX_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ORDERVOX_FILE, true );
    }
} );
```

**Rules that follow from HPOS:** never use `get_post_meta()` / `WP_Query` on orders. Always `wc_get_order()`, `$order->get_meta()`, `$order->update_meta_data()`, `wc_get_orders()`.

---

## 5. Architecture

### 5.1 Principles

- **Nothing blocks checkout.** Every hook only *enqueues* a job in Action Scheduler; sending happens in the background.
- **One send pipeline.** All messages (admin, customer, Pro) go through `Messaging\Dispatcher` → `Queue` → `Transport\CloudApiTransport`, so logging, retries, rate limits and quiet hours apply everywhere.
- **Provider-agnostic transport.** `TransportInterface` keeps Meta-specific code in one place (Twilio/360dialog adapters could be added later).
- **Extensible by design.** Pro and third parties use public filters/actions only; the free plugin never checks for Pro by class name.

### 5.2 Directory layout (free plugin)

```
ordervox/
├── ordervox.php                  # Header, constants, bootstrap, compat declarations
├── uninstall.php                 # Removes options/tables only if user opted in
├── readme.txt                    # WordPress.org readme
├── composer.json                 # PSR-4 autoload, dev tools; prod deps scoped with Strauss
├── package.json                  # @wordpress/scripts build
├── phpcs.xml.dist  phpstan.neon.dist  phpunit.xml.dist  .wp-env.json
├── languages/ordervox.pot
├── assets/                       # Built JS/CSS (committed for wp.org), icons, banners
├── src/                          # React admin app (source)
│   ├── admin/                    # Settings, Templates, Log, Wizard, Dashboard
│   └── checkout/                 # Block checkout opt-in integration (if needed beyond Additional Fields API)
├── includes/
│   ├── Plugin.php                # Service container + boot order
│   ├── Activator.php  Deactivator.php  Migrations/
│   ├── Settings/                 # Options repository, schema, defaults, encryption of secrets
│   ├── Api/                      # Meta Graph client
│   │   ├── GraphClient.php       # wp_remote_post wrapper, versioned base URL, error mapping
│   │   ├── MessagesEndpoint.php
│   │   ├── TemplatesEndpoint.php
│   │   ├── PhoneNumbersEndpoint.php
│   │   └── MediaEndpoint.php
│   ├── Transport/                # TransportInterface, CloudApiTransport
│   ├── Messaging/
│   │   ├── Dispatcher.php        # Build → enqueue
│   │   ├── Queue.php             # Action Scheduler jobs, retries/backoff
│   │   ├── MessageBuilder.php    # Template + order → API payload
│   │   ├── Placeholders/         # Registry + resolvers (Order, Customer, Product, Store, Review)
│   │   └── RateLimiter.php
│   ├── Events/                   # One class per trigger (NewOrder, PaymentReceived, StatusChanged, …)
│   ├── Webhook/                  # REST receiver, signature check, status + inbound routers
│   ├── Recipients/               # Owner recipient (free); RecipientResolver filter for Pro team/routing
│   ├── Customer/                 # Opt-in field (classic + blocks), consent storage, notifications
│   ├── Log/                      # Repository + list queries + retention cleanup
│   ├── Digest/                   # Daily summary
│   ├── Rest/                     # ordervox/v1 controllers for the admin app
│   ├── Admin/                    # Menu, asset loading, notices, order-screen meta box
│   ├── Privacy/                  # Exporter/eraser, suggested privacy text
│   └── Support/                  # Phone (E.164), Crypto, Logger, Helpers
└── tests/ (phpunit, e2e)
```

The Pro add-on mirrors this layout under `ordervox-pro/includes/` with modules: `Team`, `Routing`, `Actions` (two-way), `Inbox`, `Cod`, `AbandonedCart`, `Invoices`, `Ai`, `Analytics`, `Integrations/{Dokan,Wcfm,MultiVendorX,Subscriptions,Tracking}`, `Licensing` (Freemius).

### 5.3 Send pipeline

```
WooCommerce hook ──► Events\X::handle()            (checks toggle, builds context)
                       │
                       ▼
               Recipients\Resolver               (filter: ordervox_recipients → Pro routing)
                       │
                       ▼
               Messaging\Dispatcher::dispatch()  (dedupe key, quiet hours, writes log row = "queued")
                       │  as_enqueue_async_action('ordervox_send', [log_id])
                       ▼
               Queue::process(log_id)            (RateLimiter → MessageBuilder → Transport)
                       │
            ┌──────────┴───────────┐
       success: log "sent" + wamid  failure: classify error
                                     ├─ retryable (5xx, 429, 131048, 130429) → reschedule (1m, 5m, 30m, 2h, 12h)
                                     └─ permanent (invalid number, template missing, 131026) → "failed" + admin notice
                       ▲
Meta webhook ──► Webhook\StatusRouter            (sent → delivered → read / failed, matched by wamid)
```

**Deduplication:** each dispatch has an idempotency key `event:object_id:recipient:hash(state)` stored in the log with a unique index, so retried hooks or double status transitions never send twice.

---

## 6. WhatsApp Cloud API integration

### 6.1 Credentials (stored encrypted)

| Setting | Source in Meta |
|---|---|
| App ID | Meta App Dashboard → App settings |
| App Secret | App settings → Basic (used to verify webhook signatures) |
| WhatsApp Business Account (WABA) ID | WhatsApp → API Setup |
| Phone Number ID | WhatsApp → API Setup |
| Access token | **Permanent System User token** (Business Settings → System users) with `whatsapp_business_messaging` and `whatsapp_business_management` |
| Webhook verify token | Generated by the plugin, shown to the user to paste into Meta |
| Graph API version | Plugin constant, filterable (`ordervox_graph_version`); bump each release after testing |

The wizard rejects the 24-hour temporary token from the API Setup page with a clear explanation.

### 6.2 Endpoints used

| Purpose | Request |
|---|---|
| Send message | `POST /{phone-number-id}/messages` |
| Mark inbound message read | `POST /{phone-number-id}/messages` with `status: read` |
| List templates | `GET /{waba-id}/message_templates` |
| Create template | `POST /{waba-id}/message_templates` |
| Delete template | `DELETE /{waba-id}/message_templates?name=…` |
| Phone number info (quality rating, messaging limit tier, display name) | `GET /{phone-number-id}?fields=…` |
| Upload media (PDF invoice) | `POST /{phone-number-id}/media` |
| Subscribe app to WABA webhooks | `POST /{waba-id}/subscribed_apps` |

Base URL: `https://graph.facebook.com/{version}/`. Auth: `Authorization: Bearer {token}`. All calls go through `GraphClient`, which uses `wp_remote_request()` with a timeout of 15s, maps Meta error codes to typed exceptions, and never logs the token.

### 6.3 The 24-hour rule and templates (core constraint)

- **Business-initiated messages** (everything Ordervox sends proactively, including alerts to the store owner) must use a **Meta-approved template** unless the recipient messaged the business number within the last 24 hours.
- Inside a 24-hour window, free-form text and **interactive** messages (reply buttons, lists) are allowed.
- Ordervox therefore keeps **two layers**:
  1. **Meta template** (approved, with numbered variables `{{1}}`, `{{2}}`…, plus optional header, footer and buttons).
  2. **Ordervox template** (what the user edits): friendly placeholders like `{order_number}` mapped to those variables.
- **Template categories:** order alerts, payment, status and COD confirmations are *Utility*; abandoned-cart and review requests are *Marketing* (priced higher and require marketing opt-in).
- **Pricing:** Meta charges per delivered template message, varying by category and recipient country. The plugin shows an estimated cost per event and per month in the dashboard (Pro) using a rate table the user can edit.

**Default template pack**: on connect, the plugin offers to create its default templates in the user's WABA in the chosen language (e.g. `ordervox_new_order_v1`, `ordervox_payment_received_v1`, `ordervox_order_status_v1`, `ordervox_payment_failed_v1`, `ordervox_low_stock_v1`, `ordervox_new_review_v1`, `ordervox_customer_order_confirmed_v1`, `ordervox_daily_summary_v1`; Pro adds `ordervox_cod_confirm_v1`, `ordervox_cart_recovery_1..3_v1`, `ordervox_review_request_v1`, `ordervox_invoice_v1`, `ordervox_admin_order_actions_v1`). Approval status is polled and shown per template.

### 6.4 Owner alerts and the 24-hour window

Because owner alerts are business-initiated, they always go as templates. Two UX touches reduce friction:

- **"Keep chat open" tip:** the owner can message the business number once a day (e.g. send "hi") to open a 24h window, which unlocks richer free-form alerts and AI summaries. The plugin detects open windows from inbound webhooks and uses free-form formatting when available, falling back to the template otherwise.
- **Template-length guard:** template variables cannot contain newlines or more than four consecutive spaces, and have length limits. `MessageBuilder` sanitises variable values (newline → ` · `, truncation with "…") and the editor warns live.

### 6.5 Webhooks

- Route: `POST/GET /wp-json/ordervox/v1/webhook` (public, verified).
- **GET verification:** compare `hub.verify_token` with the stored token using `hash_equals()`, echo `hub.challenge`.
- **POST:** reject unless `X-Hub-Signature-256` equals `sha256=` + HMAC-SHA256 of the **raw** body with the App Secret (`hash_equals`). Respond `200` immediately and enqueue processing in Action Scheduler.
- Handle:
  - `statuses[]` → update log (`sent`, `delivered`, `read`, `failed` + error code).
  - `messages[]` → inbound text, `button` (template quick-reply payload), `interactive.button_reply` / `list_reply` → `Webhook\InboundRouter` → Pro actions/inbox/COD; in free, it just records the open 24h window.
  - `message_template_status_update` → update template approval status.
  - `phone_number_quality_update` → show admin notice if quality drops.
- Idempotency: store processed webhook message IDs for 48h to ignore Meta's redeliveries.

### 6.6 Phone numbers

- Normalise to E.164 using `giggsey/libphonenumber-for-php-lite`, scoped with Strauss to avoid conflicts. Default country = WooCommerce store base country; customer numbers use the billing country.
- Validate in admin settings and at checkout; store `wa_id` returned by Meta for future sends.

---

## 7. Events and triggers

All toggles default to **on** for new order, payment received, failed payment and low stock; others default **off**. Each event has: enabled toggle, recipients (owner in free; team/routing in Pro), template, and optional conditions (Pro: minimum order total, payment methods, categories).

| Event | Hook(s) | Notes |
|---|---|---|
| New order | `woocommerce_checkout_order_processed` (classic), `woocommerce_store_api_checkout_order_processed` (blocks), `woocommerce_new_order` (admin/REST-created) | Defer by 10s via Action Scheduler so line items/meta are saved; dedupe across hooks. Option: "only notify once payment is confirmed" for online gateways. |
| Payment received | `woocommerce_payment_complete` | Includes transaction ID and gateway title. |
| Status → processing / completed / cancelled / refunded | `woocommerce_order_status_changed` (`$from`, `$to`) | One handler; per-status toggle. Pro: custom statuses. Refunded also listens to `woocommerce_order_refunded` for partial refunds (amount + reason). |
| Failed payment | `woocommerce_order_status_failed` | Include gateway error note if available. |
| Low stock / out of stock | `woocommerce_low_stock`, `woocommerce_no_stock` (+ `woocommerce_product_set_stock` for variations) | Throttle: one alert per product per 24h. Option to batch into a single digest. |
| New review | `comment_post` where comment type is `review` on a `product`; `transition_comment_status` for approved | Include rating stars, excerpt, product link, verified-owner badge. |
| Daily summary | Action Scheduler recurring action at owner's chosen local time | Orders, revenue, AOV, top product, pending orders, low-stock count. |
| (Pro) Subscriptions | `woocommerce_subscription_renewal_payment_complete`, `…_failed`, `woocommerce_subscription_status_cancelled` | |
| (Pro) Abandoned cart | Custom tracker (§9.6) | |

---

## 8. Message templates and placeholders

### 8.1 Editor

- One editor per event, per language.
- Header (none / text / document), body, footer, buttons (Pro).
- Placeholder picker (searchable), live WhatsApp-style preview using the most recent real order, character counter, and validation against Meta's rules.
- "Submit to Meta" creates/updates the matching Meta template; status badge (Pending / Approved / Rejected + reason).
- Restore defaults per template.

### 8.2 Placeholders

Syntax: `{placeholder}`. Unknown placeholders render empty and are flagged in the editor.

| Group | Placeholders |
|---|---|
| Order | `{order_id}` `{order_number}` `{order_status}` `{order_date}` `{order_total}` `{order_subtotal}` `{order_tax}` `{order_shipping}` `{order_discount}` `{coupon_codes}` `{payment_method}` `{transaction_id}` `{shipping_method}` `{customer_note}` `{order_items}` `{order_items_count}` `{order_admin_url}` `{order_view_url}` `{order_pay_url}` `{refund_amount}` `{refund_reason}` |
| Customer | `{customer_first_name}` `{customer_last_name}` `{customer_full_name}` `{customer_email}` `{customer_phone}` `{billing_address}` `{shipping_address}` `{billing_country}` `{customer_order_count}` `{customer_total_spent}` |
| Product / stock | `{product_name}` `{product_sku}` `{stock_quantity}` `{product_edit_url}` |
| Review | `{review_rating}` `{review_stars}` `{review_author}` `{review_excerpt}` `{review_product}` `{review_url}` |
| Store | `{store_name}` `{store_url}` `{store_email}` `{current_date}` |
| Tracking (Pro) | `{tracking_number}` `{tracking_provider}` `{tracking_url}` |
| AI (Pro) | `{ai_summary}` `{ai_risk_level}` `{ai_risk_reasons}` |
| Meta (Pro) | `{meta:_any_order_meta_key}` and conditional blocks `{if:coupon_codes}…{/if}` |

`{order_items}` renders as `2× Blue T-shirt (M) — $40.00 · 1× Cap — $15.00`; format is filterable. Money uses `wc_price()` stripped of HTML in the order's currency.

### 8.3 Default owner "New order" body (example)

```
🛒 New order #{order_number} — {order_total}
Customer: {customer_full_name} ({customer_phone})
Items: {order_items}
Payment: {payment_method} · Ship to: {shipping_address}
```
(Stored as a Meta template with numbered variables; newlines live in the template text, not inside variables.)

---

## 9. Feature specifications

Each feature lists: behaviour, data, edge cases and acceptance criteria (AC).

### 9.1 Setup wizard (Free)

Steps: **Welcome → Connect Meta (paste credentials, with screenshots and links) → Verify (fetch phone number info) → Webhook (show callback URL + verify token, "Test webhook" button) → Owner number → Choose events → Create default templates → Send test message → Done.**

- AC: a new user can go from install to a delivered test message in under 10 minutes with credentials ready.
- AC: every step shows a specific, fixable error (e.g. "Token lacks whatsapp_business_messaging permission").

### 9.2 Connection health (Free)

Dashboard card showing: token valid, webhook last received, phone quality rating (green/yellow/red), messaging limit tier, templates approved count, queue backlog, last error. Daily background check; admin notice + email to admin on failure.

### 9.3 Message log and retries (Free/Pro)

- List table (React, server-side paging): time, event, recipient (masked), template, status timeline, cost category, error. Filters by status/event/date. Actions: resend, view payload (secrets stripped).
- Order screen meta box: all WhatsApp messages for that order.
- Retention: 30 days free; Pro configurable + CSV export.
- AC: a message failing with a retryable error is retried 5 times with backoff, then marked failed with an admin notice.

### 9.4 Customer opt-in and notifications (Free basic, Pro full)

- Checkout opt-in checkbox ("Send me order updates on WhatsApp") + optional separate marketing opt-in (required for cart recovery and review requests).
  - Block checkout: `woocommerce_register_additional_checkout_field()` (location `contact`, type `checkbox`).
  - Classic checkout: `woocommerce_checkout_fields` + save on `woocommerce_checkout_create_order`.
- Consent stored on order meta (`_ordervox_optin`, timestamp, IP hash, text shown) and on the customer (user meta) for future orders.
- My Account → "WhatsApp notifications" toggle to withdraw consent. Inbound "STOP" keyword also unsubscribes.
- Free: order confirmation + completed. Pro: all statuses, tracking info, partial refunds.
- AC: no customer message is ever sent without a stored opt-in.

### 9.5 COD confirmation (Pro)

- On new order with payment method in the configured list (default `cod`): set status to a custom **"Awaiting confirmation"** status, send `ordervox_cod_confirm_v1` template with quick-reply buttons **Confirm** / **Cancel** (payloads `cod_confirm:{order_id}:{hmac}`).
- Confirm → status to Processing + order note. Cancel → Cancelled + restock + order note. No reply after X hours → reminder; after Y hours → configurable (cancel, leave, notify owner).
- Payload HMAC prevents forged replies; the reply must come from the order's phone number.
- Metrics: confirmation rate, cancelled-by-customer, no-response.
- AC: tapping Confirm updates the order within 30 seconds of the webhook arriving.

### 9.6 Abandoned-cart recovery (Pro)

- Capture: when an opted-in shopper enters a phone at checkout (blocks: Store API cart updates; classic: AJAX on phone field blur), store cart snapshot in `ordervox_carts` with a restore token.
- Abandoned after N minutes without an order. Sequence up to 3 messages (e.g. 1h, 24h, 72h), optional auto-generated unique coupon on step 2/3.
- Restore link rebuilds the cart and pre-fills checkout; conversion attributed when an order is placed with the token.
- Stop rules: order placed, opted out, cart emptied, max sends.
- Requires marketing opt-in.
- AC: recovered revenue is shown on the dashboard and attributed per message step.

### 9.7 PDF invoices (Pro)

- If "PDF Invoices & Packing Slips" (or compatible plugin) is active, generate the invoice, upload via Media endpoint, send as document header of `ordervox_invoice_v1`. Fallback: built-in simple invoice generator.
- Trigger: configurable status (default Processing or Completed).

### 9.8 Team, roles and routing (Pro)

- Team members: name, number, language, events subscribed, quiet hours + timezone, active days.
- Routing rules (ordered, first-match or all-match): conditions on category, product, tag, shipping zone/country, payment method, order total range, vendor, customer type (new/returning). Action: send to members/groups.
- Escalation: if no action taken on a new order in X minutes, alert another member.

### 9.9 Two-way order actions (Pro)

- Admin new-order template includes quick-reply buttons: **✅ Mark processing**, **🚚 Mark shipped/completed**, **❌ Cancel** as template quick-reply buttons, plus a **📞 Call customer** phone-number button. Check Meta's current per-template button limits when building the template.
- Payload: `act:{action}:{order_id}:{nonce}` with a server-side HMAC; only accepted from numbers registered as team members with the matching permission.
- Text commands from team numbers (works inside 24h window): `order 1234`, `ship 1234 TRACKING123`, `cancel 1234`, `today`, `pending`, `stock SKU`, `help`.
- Every action adds an order note: "Marked completed via WhatsApp by {member}".
- AC: actions from an unknown number are ignored and logged.

### 9.10 WhatsApp inbox (Pro)

- wp-admin screen listing conversations from inbound webhooks, linked to customers/orders by phone.
- Reply in-window with free text, outside window with a chosen template. Media display (images, documents) via Media endpoint.
- Assign conversation to a team member; unread counters; canned replies.

### 9.11 AI features (Pro, Claude)

- **Provider:** Anthropic Claude Messages API (`https://api.anthropic.com/v1/messages`). The store owner supplies their own Anthropic API key (stored encrypted). Default model `claude-haiku-5-5` for summaries and risk (fast, low cost); `claude-sonnet-5-5` selectable for the template writer and suggested replies. Model list filterable.
- **Order summary** (`{ai_summary}`): one-line human summary, e.g. "Returning customer (5th order), gift order to a different city, express shipping."
- **Risk flag** (`{ai_risk_level}`: low/medium/high + reasons): inputs are structured, minimised signals (billing/shipping country mismatch, order velocity, failed payment count, email domain age heuristic, high-value first order, address mismatch). No full card or personal data beyond what's needed; never sends payment details.
- **Suggested replies** in inbox, **template writer** (generate + translate templates that respect Meta's rules).
- Use structured JSON output; strict timeouts (8s) and fallback to non-AI message if the call fails, so alerts are never delayed by AI.
- Disclose in readme and settings that order data is sent to Anthropic when enabled (off by default).
- Implementation note: load the `claude-api` skill before writing this module to confirm current model IDs, headers and request format.

### 9.12 Digests and analytics

- Free: daily summary at chosen time.
- Pro: weekly/monthly digests; dashboard with charts: messages by status, read rate, cost estimate, COD confirmation rate, cart recovery revenue, top events, team response time. Data from log + carts tables; WooCommerce Analytics extension point for "Orders confirmed via WhatsApp".

### 9.13 Multi-vendor (Pro)

- Dokan, WCFM, MultiVendorX: detect vendor per line item, split notifications, vendor sets their own WhatsApp number in their vendor dashboard; admin controls which events vendors may receive. Uses the store's WABA (single sender).

---

## 10. Data model

Custom tables created with `dbDelta()` and versioned migrations (`ordervox_db_version` option).

**`{prefix}ordervox_messages`** (log)

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | |
| idempotency_key | VARCHAR(191) UNIQUE | Dedupe |
| event | VARCHAR(64) | e.g. `order.new` |
| object_type / object_id | VARCHAR(32) / BIGINT | order, product, review, cart |
| recipient_type | VARCHAR(16) | owner, team, customer, vendor |
| recipient_phone | VARCHAR(32) | E.164 |
| template_name / language | VARCHAR(128) / VARCHAR(16) | |
| payload | LONGTEXT | JSON, secrets stripped |
| status | VARCHAR(16) | queued, sending, sent, delivered, read, failed, cancelled |
| wamid | VARCHAR(128) INDEX | Meta message ID |
| attempts | TINYINT | |
| error_code / error_message | VARCHAR(32) / TEXT | |
| pricing_category | VARCHAR(32) | utility, marketing, service |
| created_at / updated_at / sent_at / delivered_at / read_at | DATETIME (UTC) | |

Indexes: `(object_type, object_id)`, `(status, created_at)`, `(event, created_at)`.

**`{prefix}ordervox_webhook_events`**: `id`, `meta_id` UNIQUE, `type`, `received_at` (48h retention, dedupe).

**`{prefix}ordervox_conversations`** (Pro inbox): `phone`, `customer_id`, `last_inbound_at` (24h window tracking), `assigned_to`, `unread`.

**`{prefix}ordervox_carts`** (Pro): `id`, `token` UNIQUE, `phone`, `email`, `customer_id`, `cart_json`, `total`, `currency`, `status` (active, abandoned, recovered, lost, opted_out), `step`, `last_activity_at`, `recovered_order_id`.

**Options** (autoload only the small `ordervox_settings`): `ordervox_settings` (array), `ordervox_credentials` (encrypted), `ordervox_templates` (array), `ordervox_db_version`.

**Order meta** (HPOS-safe API): `_ordervox_optin`, `_ordervox_optin_marketing`, `_ordervox_cod_state`, `_ordervox_ai_risk`.

---

## 11. Admin UI

- Top-level menu **Ordervox** (or submenu under WooCommerce, decided at design time) with tabs: **Dashboard · Events · Templates · Recipients · Customer messages · Log · Settings · (Pro: Team, Routing, Inbox, COD, Cart recovery, AI, Analytics)**.
- Built with React + `@wordpress/components` + `@wordpress/data`, bundled by `@wordpress/scripts`, talking to `ordervox/v1` REST routes. Matches WooCommerce admin look; responsive; supports dark admin colour schemes and RTL.
- WhatsApp-style live preview pane in the template editor.
- Order edit screen meta box (HPOS + legacy screens): message history + "Send WhatsApp message" button (template picker).
- Orders list column: WhatsApp status icon (Pro: COD confirmed / awaiting).
- Accessibility: keyboard navigable, labelled controls, WCAG 2.2 AA contrast.
- Upsell surfaces (wp.org compliant): a single "Pro" tab listing features, no nagging dismissible-forever notices on other screens.

---

## 12. REST API

Namespace `ordervox/v1`. All admin routes require `manage_woocommerce` and a REST nonce.

| Method & route | Purpose |
|---|---|
| `GET/POST /settings` | Read/update settings (secrets write-only, returned masked) |
| `POST /connection/verify` | Validate credentials, fetch phone info |
| `POST /connection/test-message` | Send test template to a number |
| `GET /templates` · `POST /templates/{id}` · `POST /templates/{id}/submit` · `POST /templates/sync` | Template CRUD and Meta sync |
| `GET /events` · `POST /events/{event}` | Toggles and per-event config |
| `GET /messages` · `POST /messages/{id}/resend` | Log |
| `GET /health` | Health card data |
| `GET/POST /webhook` | Public Meta webhook (signature-verified, no nonce) |
| Pro: `/team`, `/routing`, `/inbox/*`, `/carts`, `/analytics`, `/ai/test` | |

---

## 13. Security

- Capabilities: `manage_woocommerce` for all settings; custom `ordervox_manage_inbox` for Pro inbox agents (granted to shop managers by default).
- Nonces on every admin form/REST call; `sanitize_*` on input, `esc_*` on output; `$wpdb->prepare()` for every custom query.
- **Secrets at rest:** access token, App Secret and Anthropic key encrypted with libsodium (`sodium_crypto_secretbox`) using a key derived from `wp_salt('auth')` (or `ORDERVOX_ENCRYPTION_KEY` constant if defined). Never returned to the browser in full, never logged.
- Webhook: HMAC signature verification with `hash_equals`, raw body read via `$request->get_body()`, size limit, rejects stale/duplicate events.
- Button payloads and restore tokens: HMAC-signed, single-use where applicable, bound to the expected phone number.
- Rate limiting on public endpoints (cart capture, webhook) via transients per IP.
- Outbound HTTP only to `graph.facebook.com` and `api.anthropic.com` (Pro AI); no other external calls, no remote code or assets.
- Run `security-audit` / `security-review` before every release (§21).

---

## 14. Privacy and compliance

- **WhatsApp Business Messaging Policy:** customers must opt in before receiving messages; opt-out honoured immediately; marketing requires separate opt-in.
- **GDPR / UK GDPR / CCPA-CPRA / LGPD / POPIA / NDPR:** consent records, data minimisation (masked numbers in logs), configurable log retention, WordPress personal data exporter and eraser hooks (`wp_privacy_personal_data_exporters` / `_erasers`) covering log rows, carts and conversations, and suggested privacy policy text via `wp_add_privacy_policy_content()`.
- **Third-party disclosure** in `readme.txt` (required by WordPress.org): Meta WhatsApp Cloud API (what is sent, when, links to Meta terms and privacy policy); Anthropic API (Pro, opt-in).
- No telemetry unless the user opts in (Freemius opt-in screen allows "Skip").

---

## 15. Internationalisation

- All strings wrapped with `__()`/`esc_html__()` etc. in the `ordervox` text domain; JS strings via `@wordpress/i18n` + `wp_set_script_translations()`.
- Ship `ordervox.pot`; translations delivered through translate.wordpress.org.
- Template languages map to Meta language codes (`en_US`, `fr`, `es`, `pt_BR`, `ar`, `de`, …). Language chosen per recipient: customer → order language (WPML/Polylang/TranslatePress meta or site locale); team member → their setting.
- Currency, dates and numbers formatted using the order currency and site locale/timezone (`wp_date()`).
- RTL admin support.

---

## 16. Performance

- No synchronous API calls on the frontend or during checkout.
- Assets loaded only on Ordervox screens; checkout adds no JS for the opt-in in block checkout (uses Additional Checkout Fields API) and ≤2 KB in classic.
- Rate limiter respects the number's messaging throughput; queue concurrency configurable.
- Log cleanup runs daily in batches of 500.
- Settings option autoloaded; large data (templates, credentials) not autoloaded.

---

## 17. Developer extensibility

Documented hooks (prefix `ordervox_`):

| Hook | Type | Purpose |
|---|---|---|
| `ordervox_events` | filter | Register custom events |
| `ordervox_recipients` | filter | Change recipients for an event (Pro routing uses this) |
| `ordervox_placeholders` | filter | Register custom placeholders + resolvers |
| `ordervox_order_items_format` | filter | Format of `{order_items}` |
| `ordervox_message_payload` | filter | Final API payload before send |
| `ordervox_should_send` | filter | Veto a send |
| `ordervox_before_send` / `ordervox_after_send` | action | Observe sends |
| `ordervox_message_status_changed` | action | Delivery/read/failed updates |
| `ordervox_inbound_message` | action | Inbound messages (Pro modules attach here) |
| `ordervox_button_reply` | action | Quick-reply payloads |
| `ordervox_graph_version` | filter | API version override |
| `ordervox_admin_tabs` | filter | Add admin tabs (Pro) |

Also: WP-CLI commands `wp ordervox test <phone>`, `wp ordervox queue run`, `wp ordervox templates sync`, `wp ordervox log purge`.

---

## 18. Testing and quality

| Layer | Tooling |
|---|---|
| Coding standards | PHPCS with WordPress-Extra + WooCommerce sniffs + PHPCompatibilityWP (8.1+) |
| Static analysis | PHPStan level 6+ with `szepeviktor/phpstan-wordpress` and WooCommerce stubs |
| Unit tests | PHPUnit + Brain Monkey (placeholders, MessageBuilder, phone normalisation, signature verification, retry classifier) |
| Integration tests | PHPUnit inside `wp-env` with WooCommerce (events fire, HPOS on and off, dedupe) |
| API tests | Mocked Graph responses via `pre_http_request` filter (success, 4xx, 5xx, rate limits, template errors) |
| E2E | Playwright against `wp-env`: wizard, classic + block checkout opt-in, order → log row, webhook simulation (signed fixtures) |
| Plugin Check | Official "Plugin Check" (PCP) plugin must pass with zero errors |
| Manual | Real Meta test number on your WooCommerce test site: every event, template approval, delivery/read webhooks |
| Compatibility matrix | PHP 8.1–8.4 × WP latest/−1 × WC latest/−1, HPOS on/off, classic/block checkout |

CI: GitHub Actions running PHPCS, PHPStan, PHPUnit, JS lint/build, Playwright, and building a release zip (excluding dev files via `.distignore`).

---

## 19. WordPress.org submission rules

Key guidelines that shape the architecture:

1. **No trialware / locked features** in the free plugin. Pro code lives only in `ordervox-pro`.
2. **Upsells** allowed but must not hijack the admin; no misleading notices.
3. **No tracking without consent.** Freemius opt-in must be skippable.
4. **External services disclosed** in readme with links to terms/privacy.
5. **No obfuscated code**; built JS must ship with its source (`src/`) or a link to the public repo.
6. **Unique prefixes** for everything global.
7. **Trademarks:** WooCommerce and WhatsApp used only descriptively, with a non-affiliation notice (see §2).
8. **GPLv2-or-later** licence, compatible bundled libraries only.
9. Use `wp_remote_*` for HTTP, WordPress-bundled jQuery/React (no CDN).
10. Readme: Stable tag, Tested up to, Requires PHP, `Requires Plugins: woocommerce`, screenshots, FAQ (Meta setup, costs, 24-hour rule).

---

## 20. Build phases and milestones

Each phase ends with passing tests, PHPCS/PHPStan clean, and a manual check on the test site.

### Phase 0 — Foundations (week 1)
- Register ordervox domains and social handles; run trademark searches (§2).
- Scaffold plugin, Composer (PSR-4, Strauss), `@wordpress/scripts`, `wp-env` with WooCommerce, PHPCS/PHPStan/PHPUnit, GitHub repo + CI.
- HPOS + blocks compatibility declarations, activation/migrations, settings repository with encryption.
- **AC:** plugin activates cleanly on test site; CI green.

### Phase 1 — Cloud API core (weeks 2–3)
- `GraphClient`, Messages/Templates/PhoneNumbers endpoints, error mapping.
- Queue + Dispatcher + log table + retries + dedupe.
- Webhook endpoint (verify + signature + statuses).
- Setup wizard, health card, test message.
- **AC:** test template sent from wizard; delivered/read statuses appear in log.

### Phase 2 — Events & templates (weeks 3–4)
- All 8 admin events + toggles; placeholder engine; template editor with preview; default template pack creation & approval tracking; daily summary.
- Order meta box and orders list column.
- **AC:** every event produces the correct message on the test site with HPOS on and off.

### Phase 3 — Customer messaging (week 5)
- Opt-in (classic + blocks), consent storage, My Account toggle, STOP keyword, customer confirmation + completed messages, privacy exporter/eraser.
- **AC:** no message without consent; opt-out stops all sends immediately.

### Phase 4 — Free release (week 6)
- i18n, accessibility pass, readme, screenshots, banner/icon, Plugin Check, security audit.
- Submit to WordPress.org.

### Phase 5 — Pro foundation (weeks 7–8)
- `ordervox-pro` scaffold, Freemius licensing, admin tab injection.
- Team members, routing rules, quiet hours, custom statuses, conditional templates, multi-language.

### Phase 6 — Two-way & COD (weeks 9–10)
- Inbound router, action buttons, text commands, COD confirmation flow, inbox.

### Phase 7 — Revenue features (weeks 11–12)
- Abandoned-cart capture + sequences + coupons + attribution; PDF invoices; review requests; tracking integrations.

### Phase 8 — AI & analytics (weeks 13–14)
- Claude integration (summary, risk, suggested replies, template writer); analytics dashboard; digests; cost estimator.

### Phase 9 — Ecosystem & launch (weeks 15–16)
- Dokan/WCFM/MultiVendorX, Subscriptions, outbound webhooks, WP-CLI; docs site, landing page, pricing; Pro release.

**Suggested Pro pricing (to validate):** Starter (1 site) / Business (5 sites) / Agency (unlimited), annual + lifetime option, 14-day refund.

---

## 21. Claude Code skills and plugins to use

Installed and relevant:

| Skill / agent | Use it for | Phase |
|---|---|---|
| `wordpress-devkit:plugin-scaffold` | Secure plugin scaffold (header, structure, prefixes) | 0, 5 |
| `anthropic-skills:wp-plugin-development` | WordPress plugin architecture and best practices | All |
| `wordpress-pro` | WooCommerce hooks, REST API, security hardening, performance | 1–9 |
| `wordpress-devkit:db-migration` | Custom tables with `dbDelta` and versioned migrations | 0, 7 |
| `wordpress-devkit:gutenberg-block` / `block-author` agent | Any block-editor UI (e.g. a "WhatsApp chat" block if added) | Optional |
| `wordpress-devkit:doctor` | Check PHP / wp-cli / composer toolchain locally | 0 |
| `wordpress-devkit:wp-debug` / `wp-forensics` agent | Diagnose fatal errors on the test site | Any |
| `wordpress-devkit:security-audit` / `hardening-check` | Pre-release security pass | 4, 9 |
| `security-review` | Review webhook, encryption, REST permission code | 1, 6 |
| `code-review` | Review each phase's diff for bugs | Every phase |
| `claude-api` | Correct Claude API usage for AI features | 8 |
| `frontend-design` / `senior-frontend` | React admin app look and feel | 1–3 |
| `design:ux-copy` | Wizard, error and opt-in wording | 1, 3 |
| `design:accessibility-review` | Admin UI accessibility pass | 4 |
| `product-management:write-spec` | Further feature specs | Any |
| `hostinger:deploy-to-hosting` | Deploy builds to the test site if it is on Hostinger | Any |
| `marketing:content-creation`, `searchfit-seo:*` | Readme, landing page, docs, SEO | 4, 9 |
| `wordpress-devkit:upgrade-planner` agent | Compatibility planning for new WP/WC versions | Post-launch |

Enabled in the account but its skills were not loaded into this session (start a new session to pick them up): **WordPress Dev Skills** — `wp-woocommerce`, `wp-background-processing` (Action Scheduler), `wp-build-tools`, `wp-database`, `wp-coding-standards`, `wp-phpstan-stubs`, `wp-plugin-testing`, `wp-ci-qa`, `wp-i18n-workflow`, `wp-freemius`, `wp-org-submission`, `wp-plugin-release`, `wp-plugin-audit`, `wp-github-flow`. These map directly onto §16–§20.

Available to install: **wordpress-mcp** (manage the test WooCommerce site — create test orders, products, reviews — straight from Claude Code via the WordPress MCP Adapter).

---

## 22. Store-owner setup guide (Meta side)

To include in docs and the wizard:

1. Create a **Meta Business portfolio** (business.facebook.com) and verify the business (needed to raise messaging limits and use a display name).
2. At developers.facebook.com create an app of type **Business**, add the **WhatsApp** product.
3. Add and verify a **dedicated phone number** not currently used on the WhatsApp or WhatsApp Business app (or migrate it), set the display name.
4. Add a **payment method** in WhatsApp Manager (needed beyond free service conversations).
5. Create a **System User** (Business settings → Users → System users), assign the app and WABA, generate a **permanent token** with `whatsapp_business_messaging` and `whatsapp_business_management`.
6. Copy **Phone Number ID**, **WABA ID**, **App ID**, **App Secret** and token into the Ordervox wizard.
7. In the app's WhatsApp → Configuration, set the **callback URL** and **verify token** shown by Ordervox, and subscribe to the `messages` and `message_template_status_update` fields.
8. Let Ordervox create the default templates and wait for approval (usually minutes).
9. Send a test message.

During development you can use Meta's free **test number** and add up to five recipient numbers in API Setup.

---

## 23. Open questions

1. **Trademark search and domain registration for Ordervox** (§2). Do before public launch.
2. Menu placement: top-level "Ordervox" vs under WooCommerce.
3. Meta **Tech Provider** status for Embedded Signup (one-click connect): requires business verification of Webbcode Studios and app review; worth starting early as it is a major Pro selling point.
4. AI: user-supplied Anthropic key (simplest, chosen in this spec) vs a Webbcode-hosted proxy with included credits (better UX, ongoing cost and data-processing obligations).
5. Pro licensing: Freemius (assumed) vs Lemon Squeezy/Easy Digital Downloads.
6. Support channel and docs site domain.
7. Test site hosting details (for deploy automation and webhooks: must be public HTTPS; for local dev use a tunnel such as Cloudflare Tunnel or ngrok).
