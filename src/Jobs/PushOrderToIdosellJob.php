<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Lunar\Models\Order;
use WizcodePl\LunarIdosell\Actions\PushOrderToIdosell;

/**
 * Queues the heavy `Idosell createOrder` HTTP call so the storefront's
 * checkout response stays fast.
 *
 * Per-order lock prevents the (rare) case of `OrderPlaced` firing twice
 * for the same order — we never want to create the same Idosell order
 * twice.
 */
class PushOrderToIdosellJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function handle(PushOrderToIdosell $push): void
    {
        Cache::lock(sprintf('lunar-idosell:order:%d', $this->order->getKey()), 30)
            ->block(10, fn () => $push($this->order->fresh() ?? $this->order));
    }
}
