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
use WizcodePl\LunarIdosell\Actions\UpdateOrderStatusInIdosell;

class UpdateOrderStatusInIdosellJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function handle(UpdateOrderStatusInIdosell $update): void
    {
        // Same lock key as `PushOrderToIdosellJob` so we don't race a
        // status update with a still-in-flight push.
        Cache::lock(sprintf('lunar-idosell:order:%d', $this->order->getKey()), 30)
            ->block(10, fn () => $update($this->order->fresh() ?? $this->order));
    }
}
