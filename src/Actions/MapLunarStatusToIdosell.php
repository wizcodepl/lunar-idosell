<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Actions;

use WizcodePl\LunarIdosell\Enums\IdosellOrderStatus;

/**
 * Translates Lunar's order status string into the Idosell status we want
 * the remote order to land in.
 *
 *   awaiting-payment → new
 *   paid             → paid
 *   cancelled        → cancelled
 *   refunded         → cancelled  (Idosell has no separate refunded status;
 *                                  we reflect the cancellation and rely on
 *                                  the merchant to issue the refund there)
 *
 * Returns `null` for unknown / non-actionable Lunar statuses — the caller
 * should treat that as "no push needed".
 */
class MapLunarStatusToIdosell
{
    public function __invoke(string $lunarStatus): ?IdosellOrderStatus
    {
        return match ($lunarStatus) {
            'awaiting-payment' => IdosellOrderStatus::New,
            'paid' => IdosellOrderStatus::Paid,
            'cancelled', 'refunded' => IdosellOrderStatus::Cancelled,
            default => null,
        };
    }
}
