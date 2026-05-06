<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use WizcodePl\LunarIdosell\Enums\IdosellEntityType;
use WizcodePl\LunarIdosell\Enums\IdosellLinkStatus;

/**
 * One row per Lunar entity that has been touched by the Idosell integration
 * (pulled product / variant, pushed order). Tracks the latest sync state.
 *
 * Not append-only — each sync upserts the same row by `(entity_type, entity_id)`.
 * For history, look at Laravel logs filtered by the `lunar-idosell` channel.
 *
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $idosell_id
 * @property IdosellLinkStatus $last_status
 * @property Carbon $last_synced_at
 * @property string|null $last_error
 * @property string|null $last_payload_hash
 * @property array<string, mixed>|null $meta
 */
class IdosellLink extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'entity_type',
        'entity_id',
        'idosell_id',
        'last_status',
        'last_synced_at',
        'last_error',
        'last_payload_hash',
        'meta',
    ];

    /**
     * Map short discriminator strings to Lunar model classes. Polymorphic
     * relation reads/writes `entity_type` as the short key and resolves
     * to/from the actual class via this map.
     *
     * @var array<string, class-string>
     */
    public static array $morphMap = [
        'product' => Product::class,
        'variant' => ProductVariant::class,
        'order' => Order::class,
    ];

    public function getTable()
    {
        return config('lunar.database.table_prefix').'idosell_links';
    }

    protected function casts(): array
    {
        return [
            'last_status' => IdosellLinkStatus::class,
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Polymorphic back-reference to the Lunar entity (Product / Variant / Order).
     * Resolves through `$morphMap` so `entity_type` stays as a short key.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    /**
     * Find an existing link for the given Lunar entity, or null if none
     * has been recorded yet.
     */
    public static function findFor(Product|ProductVariant|Order $entity): ?self
    {
        return static::query()
            ->where('entity_type', static::typeFor($entity)->value)
            ->where('entity_id', $entity->getKey())
            ->first();
    }

    /**
     * Convenience: which `IdosellEntityType` matches the given model.
     */
    public static function typeFor(Product|ProductVariant|Order $entity): IdosellEntityType
    {
        return match (true) {
            $entity instanceof Product => IdosellEntityType::Product,
            $entity instanceof ProductVariant => IdosellEntityType::Variant,
            $entity instanceof Order => IdosellEntityType::Order,
        };
    }
}
