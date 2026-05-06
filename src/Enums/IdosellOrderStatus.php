<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Enums;

/**
 * Subset of Idosell order statuses we use for outbound mapping. Idosell has
 * many more, but for a minimal Lunar→Idosell push these four cover what
 * Lunar's order lifecycle produces.
 *
 * Reference: https://idosell.readme.io/reference/orders-orders-status (or
 * the equivalent in your panel under Settings → Orders → Statuses).
 */
enum IdosellOrderStatus: string
{
    case New = 'new';
    case Paid = 'paid';
    case FinishedPaid = 'finished_paid';
    case Cancelled = 'cancelled';
}
