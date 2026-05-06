<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Observers;

use Lunar\Models\Order;
use WizcodePl\LunarIdosell\Jobs\PushOrderToIdosellJob;
use WizcodePl\LunarIdosell\Jobs\UpdateOrderStatusInIdosellJob;

/**
 * Hooks Lunar Order lifecycle into the Idosell push pipeline.
 *
 * Lunar 1.3 has no public `OrderPlaced` event, so we lean on Eloquent's
 * native `created` / `updated` observer hooks. `Order::observe(...)` is
 * registered from the package's service provider.
 */
class OrderObserver
{
    public function created(Order $order): void
    {
        if (! config('lunar-idosell.sync.orders_on_placed', true)) {
            return;
        }

        PushOrderToIdosellJob::dispatch($order);
    }

    public function updated(Order $order): void
    {
        if (! config('lunar-idosell.sync.orders_on_status_change', true)) {
            return;
        }

        if (! $order->wasChanged('status')) {
            return;
        }

        UpdateOrderStatusInIdosellJob::dispatch($order);
    }
}
