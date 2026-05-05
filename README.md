# lunar-idosell

Idosell integration for [Lunar PHP](https://lunarphp.io). Customers buy on Idosell, stock and orders flow back into Lunar in real time.

> ⚠️ **Pre-1.0 — scaffold only.** The package is set up with CI, lint and static analysis, but the Idosell sync itself is not yet implemented. Track progress in [CHANGELOG.md](CHANGELOG.md).

## Planned scope

- Bi-directional sync: products, variants, stock, prices, orders, order statuses
- Idosell REST API client (token auth, pagination, retries)
- Configurable sync direction per resource (Lunar → Idosell, Idosell → Lunar, both)
- Queueable sync jobs + Artisan commands for bulk imports
- Real-time order webhook receiver
- Per-channel mapping of Idosell storefronts to Lunar channels

## Requirements

- PHP 8.2+
- Lunar core ^1.3

## Install

```bash
composer require wizcodepl/lunar-idosell
```

The service provider auto-registers via Laravel package discovery.

## License

MIT — see [LICENSE](LICENSE).
