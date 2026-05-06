<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;
use WizcodePl\LunarIdosell\Models\IdosellLink;

/**
 * Dispatched after a Lunar Order has been successfully created in Idosell.
 * Listen for this to send confirmations, kick off fulfilment, etc.
 */
class IdosellOrderPushed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly IdosellLink $link,
    ) {}
}
