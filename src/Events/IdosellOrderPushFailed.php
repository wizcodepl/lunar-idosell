<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;
use WizcodePl\LunarIdosell\Models\IdosellLink;

/**
 * Dispatched when a Lunar Order push to Idosell fails (HTTP error,
 * unmapped variant, network outage). The link row carries the last
 * error message; the original exception lives in `$reason`.
 */
class IdosellOrderPushFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly IdosellLink $link,
        public readonly string $reason,
    ) {}
}
