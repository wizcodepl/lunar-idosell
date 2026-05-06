<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Enums;

/**
 * Discriminator for the polymorphic `entity_type` column on `idosell_links`.
 * Class name on the Lunar side (`Lunar\Models\Product`, etc.) lives in
 * `morphMap()` of `IdosellLink` — these are the short keys.
 */
enum IdosellEntityType: string
{
    case Product = 'product';
    case Variant = 'variant';
    case Order = 'order';
}
