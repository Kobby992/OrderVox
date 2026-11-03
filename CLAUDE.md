# Ordervox

WooCommerce plugin by Webbcode Studios that sends store alerts to WhatsApp via the official WhatsApp Cloud API. The full product spec and build phases are in `ORDERVOX-BUILD-SPEC.md` (§20 lists the phases).

## Workflow

- After finishing each build phase, commit with a `Phase N: …` message and push to https://github.com/Kobby992/OrderVox (`origin`, branch `main`). No need to ask first.
- PHP is not installed on the development machine. PHPCS, PHPStan and PHPUnit run in GitHub Actions (`.github/workflows/ci.yml`); locally, only `npm run build` and `npm run lint:js` can run.

## Conventions

- Namespace `Webbcode\Ordervox`, PSR-4 under `includes/`; prefixes `ordervox_` / `ORDERVOX_`; text domain `ordervox`; REST namespace `ordervox/v1`.
- WordPress coding standards (tabs, snake_case methods, Yoda conditions, docblocks on everything in `includes/`).
- Orders only through WooCommerce CRUD (`wc_get_order()`, `$order->get_meta()`), never post meta, so HPOS keeps working.
- Every outbound message goes through `Messaging\Dispatcher` → `Messaging\Queue` (Action Scheduler); nothing sends during checkout.
- Secrets are stored through `Settings\Credentials` (encrypted with `Support\Crypto`) and never returned unmasked to the browser.
- Pro features live in the separate `ordervox-pro` plugin and attach through `ordervox_*` hooks; no locked code in this plugin (WordPress.org rule).
- Admin UI is React in `src/admin/`, built with `@wordpress/scripts` to `build/`.
