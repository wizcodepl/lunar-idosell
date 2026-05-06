<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Actions;

use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Throwable;
use WizcodePl\LunarIdosell\Enums\IdosellLinkStatus;
use WizcodePl\LunarIdosell\IdosellClient;
use WizcodePl\LunarIdosell\Models\IdosellLink;

/**
 * Pushes a status update for an already-pushed Lunar Order.
 *
 * Idempotent: no-op if the previously pushed Idosell status equals the
 * mapped one we'd push now (we track the last pushed status in the
 * link's `meta.last_pushed_status` so we don't burn API calls on repeats).
 *
 * If the order has never been pushed (no link / link without
 * `idosell_id`), this is a silent no-op — `PushOrderToIdosell` should
 * have run first.
 */
class UpdateOrderStatusInIdosell
{
    public function __construct(
        private readonly IdosellClient $client,
        private readonly MapLunarStatusToIdosell $statusMapper,
    ) {}

    public function __invoke(Order $order): ?IdosellLink
    {
        $link = IdosellLink::findFor($order);
        if ($link === null || $link->idosell_id === '') {
            return null;
        }

        $target = ($this->statusMapper)((string) $order->status);
        if ($target === null) {
            return null;
        }

        $lastPushed = (string) (($link->meta['last_pushed_status'] ?? null) ?? '');
        if ($lastPushed === $target->value) {
            // Already in this state on Idosell side — skip.
            return $link;
        }

        try {
            $this->client->updateOrderStatus([
                'params' => [
                    'orders' => [
                        [
                            'orderSerialNumber' => $link->idosell_id,
                            'orderStatus' => $target->value,
                        ],
                    ],
                ],
            ]);

            $link->update([
                'last_status' => IdosellLinkStatus::Success,
                'last_synced_at' => now(),
                'last_error' => null,
                'meta' => array_merge((array) $link->meta, [
                    'last_pushed_status' => $target->value,
                ]),
            ]);

            return $link;
        } catch (Throwable $e) {
            $link->update([
                'last_status' => IdosellLinkStatus::Failed,
                'last_synced_at' => now(),
                'last_error' => $e->getMessage(),
            ]);

            Log::channel((string) config('lunar-idosell.log_channel', 'stack'))->error(
                'lunar-idosell | failed to update order status',
                ['order_id' => $order->getKey(), 'target' => $target->value, 'error' => $e->getMessage()],
            );

            return $link;
        }
    }
}
