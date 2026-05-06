# Changelog

All notable changes to `wizcodepl/lunar-idosell` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Idosell → Lunar product pull** — `php artisan idosell:sync-products` pulls products + variants + prices + stocks for the configured shop and warehouse. Idempotent, safe to re-run as a cron.
- **Lunar → Idosell order push** — every Lunar Order created triggers a queued `PushOrderToIdosellJob` that creates the order in Idosell. Customer is created/matched on Idosell side from order email.
- **Lunar → Idosell status sync** — `OrderObserver::updated` watches Lunar order status changes and dispatches `UpdateOrderStatusInIdosellJob` when it changes. Status mapping: `awaiting-payment → new`, `paid → paid`, `cancelled → cancelled`, `refunded → cancelled`.
- **Polymorphic link table** (`lunar_idosell_links`) — single table tracking the Idosell counterpart of any Lunar Product / ProductVariant / Order, plus its last sync state. No history; each sync upserts the row.
- **`IdosellLink` model** with `morphTo('entity')` and `findFor($lunarEntity)` helper.
- **Per-order `Cache::lock`** on push + status jobs — prevents the (rare) double-fire from creating two Idosell orders for one Lunar order.
- **DB transaction** wrapping the Lunar entity write + audit row write inside upsert action.
- **Idempotency** via `last_payload_hash` on each link row — re-syncs that wouldn't change anything skip the write.
- **Domain events** — `IdosellOrderPushed`, `IdosellOrderPushFailed` for downstream listeners (mails, Slack, etc.).
- **CI** — PHPUnit / Pint / PHPStan level 5 (Larastan), matrix PHP 8.3 / 8.4 / 8.5 × Laravel 11 / 12.

### Notes
- This is a one-way integration on each side: products flow Idosell → Lunar (pull only); orders flow Lunar → Idosell (push only). Idosell webhook receiver (e.g. "order shipped" pushed back to Lunar) is **not** in v1.0 scope.
- A queue worker (`queue:work` or Horizon) must be running for order push / status sync to execute.
