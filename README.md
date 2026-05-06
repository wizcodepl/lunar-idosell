<p align="center">
  <img src="art/logo.svg" alt="Lunar Idosell" width="200">
</p>

# lunar-idosell

Idosell ↔ [Lunar PHP](https://lunarphp.io) integration. Pulls products + variants + prices + stocks from Idosell into Lunar; pushes orders + order statuses from Lunar to Idosell.

## Scope

- **Idosell → Lunar (pull)** — products, variants ("sizes"), prices for the configured shop, stock levels for the configured warehouse, names + descriptions in the configured language, image URLs (referenced, not downloaded), categories as flat `Lunar Collection` rows.
- **Lunar → Idosell (push)** — every Lunar Order created is pushed to Idosell. Subsequent status changes (`awaiting-payment` → `paid` → `cancelled`) are pushed as Idosell status updates.

## What's intentionally not in v1.0

- Multi-shop sync (single `shop_id` per install)
- Multi-warehouse stocks
- Image downloads to Lunar's MediaLibrary
- Hierarchy of Idosell categories (we pull a flat list)
- Multi-language (one language per install)
- Refunds back to Idosell as a distinct status (refunded → cancelled with note)
- Customer push (`clients/clients/add`) — Idosell creates clients from order email
- Idosell webhook receiver (Idosell → Lunar, e.g. "order shipped") — out of scope, add in 1.1

## Requirements

- PHP 8.3+
- Lunar core ^1.3
- A queue worker (`php artisan queue:work` or Horizon) — order push + status sync run as queued jobs

## Install

```bash
composer require wizcodepl/lunar-idosell
php artisan vendor:publish --tag=lunar-idosell-config
php artisan vendor:publish --tag=lunar-idosell-migrations
php artisan migrate
```

## Configure

```env
IDOSELL_API_BASE_URL=https://your-shop.idosell.com
IDOSELL_API_KEY=...
IDOSELL_SHOP_ID=1
IDOSELL_WAREHOUSE_ID=1
IDOSELL_LANGUAGE=pl
IDOSELL_LUNAR_CHANNEL_ID=1
```

Generate the API key in Idosell merchant panel → Configuration → API → API Keys.

## Pull products

```bash
php artisan idosell:sync-products
```

Idempotent: re-running updates existing products by their stored Idosell ID. Run on a cron (e.g. every 30 min). Each product's last sync state lands in `lunar_idosell_links`.

## Push orders

Automatic. Every `Lunar\Models\Order` that's created (via cart-to-order flow) triggers the `OrderObserver::created` hook, which dispatches `PushOrderToIdosellJob`. The job:

1. Builds the Idosell payload from the order's billing address + line items.
2. Maps Lunar variants to Idosell size codes via the `idosell_links` table — **products must be pulled before orders push**, otherwise we'd send an order Idosell can't fulfil.
3. POSTs to `/api/admin/v6/orders/orders`.
4. Persists the Idosell `orderId` in the link row.

If you want to disable automatic pushing, set:

```env
IDOSELL_PUSH_ORDERS_ON_PLACED=false
IDOSELL_PUSH_ORDERS_ON_STATUS_CHANGE=false
```

## Listening to events

```php
use WizcodePl\LunarIdosell\Events\IdosellOrderPushed;
use WizcodePl\LunarIdosell\Events\IdosellOrderPushFailed;

Event::listen(IdosellOrderPushed::class, function (IdosellOrderPushed $e) {
    // $e->order, $e->link
});

Event::listen(IdosellOrderPushFailed::class, function (IdosellOrderPushFailed $e) {
    // $e->order, $e->link, $e->reason — alert ops, slack, whatever
});
```

If your listener is heavy (Slack notification, etc.), implement `ShouldQueue` — events are already serializable.

## How sync state is tracked

One polymorphic table — `lunar_idosell_links` — tracks the Idosell counterpart of every Lunar entity we've touched.

```php
use WizcodePl\LunarIdosell\Models\IdosellLink;

$link = IdosellLink::findFor($order);   // or product / variant
// $link->idosell_id          — remote ID
// $link->last_status         — IdosellLinkStatus::Success / Failed
// $link->last_synced_at
// $link->last_error          — populated only when last_status === Failed
// $link->meta['last_pushed_status']  — only on order links
```

No history kept — each sync overwrites the row. Forensics live in Laravel logs (channel configurable via `IDOSELL_LOG_CHANNEL`).

## Testing

```bash
composer install
composer test       # tests skip cleanly if IDOSELL_API_BASE_URL / IDOSELL_API_KEY aren't set
composer format     # Pint
composer analyse    # PHPStan level 5 (Larastan)
```

For e2e against a real Idosell merchant:

```bash
export IDOSELL_API_BASE_URL=https://your-shop.idosell.com
export IDOSELL_API_KEY=...
composer test
```

## License

MIT — see [LICENSE](LICENSE).
