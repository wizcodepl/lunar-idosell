<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Throwable;
use WizcodePl\LunarIdosell\Enums\IdosellEntityType;
use WizcodePl\LunarIdosell\Enums\IdosellLinkStatus;
use WizcodePl\LunarIdosell\Models\IdosellLink;

/**
 * Upserts a single Idosell product (with its variants) into Lunar.
 *
 * Side effects:
 *   - Lunar `Product` row created or updated (matched by Idosell `productId`
 *     via the `idosell_links` table; if no link exists, a new product is
 *     created).
 *   - Lunar `ProductVariant` rows for each Idosell size, idempotent on
 *     Idosell size code.
 *   - `idosell_links` rows for product + each variant updated with the
 *     latest sync state.
 *
 * Idempotency: every meaningful change is guarded by `last_payload_hash`.
 * If the incoming Idosell payload hashes to the same value as last time,
 * we skip the write (still touch `last_synced_at` so dashboards stay current).
 *
 * Scope of v1.0: only the fields Lunar's `Product` / `ProductVariant`
 * actually have. Idosell-specific things (tax classes, shipping templates,
 * SEO meta) are read but not persisted.
 */
class UpsertProductFromIdosell
{
    /**
     * @param array<string, mixed> $idosellProduct Single product from `getProducts` response.
     */
    public function __invoke(array $idosellProduct): IdosellLink
    {
        $idosellId = (string) ($idosellProduct['productId'] ?? '');
        if ($idosellId === '') {
            throw new \InvalidArgumentException('Idosell product is missing `productId`');
        }

        $hash = sha1(json_encode($idosellProduct, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($idosellProduct, $idosellId, $hash) {
                $existing = $this->findExistingLink($idosellId);

                if ($existing && $existing->last_payload_hash === $hash) {
                    // No change since last successful sync — only refresh
                    // the timestamp so monitoring sees the heartbeat.
                    $existing->update([
                        'last_status' => IdosellLinkStatus::Success,
                        'last_synced_at' => now(),
                        'last_error' => null,
                    ]);

                    return $existing;
                }

                $product = $this->upsertLunarProduct($idosellProduct, $existing?->entity_id);
                $this->upsertVariants($product, (array) ($idosellProduct['sizes'] ?? []));

                return IdosellLink::query()->updateOrCreate(
                    [
                        'entity_type' => IdosellEntityType::Product->value,
                        'entity_id' => $product->getKey(),
                    ],
                    [
                        'idosell_id' => $idosellId,
                        'last_status' => IdosellLinkStatus::Success,
                        'last_synced_at' => now(),
                        'last_error' => null,
                        'last_payload_hash' => $hash,
                    ],
                );
            });
        } catch (Throwable $e) {
            Log::channel((string) config('lunar-idosell.log_channel', 'stack'))->warning(
                'lunar-idosell | failed to upsert product',
                ['idosell_id' => $idosellId, 'error' => $e->getMessage()],
            );

            // Best-effort: record the failure on the link row (or create one)
            // so operators can find what's broken without grep-ing logs.
            return IdosellLink::query()->updateOrCreate(
                [
                    'entity_type' => IdosellEntityType::Product->value,
                    'entity_id' => 0,  // sentinel: no Lunar entity created
                ],
                [
                    'idosell_id' => $idosellId,
                    'last_status' => IdosellLinkStatus::Failed,
                    'last_synced_at' => now(),
                    'last_error' => $e->getMessage(),
                    'last_payload_hash' => null,
                ],
            );
        }
    }

    private function findExistingLink(string $idosellId): ?IdosellLink
    {
        return IdosellLink::query()
            ->where('entity_type', IdosellEntityType::Product->value)
            ->where('idosell_id', $idosellId)
            ->where('entity_id', '!=', 0)
            ->first();
    }

    /**
     * @param array<string, mixed> $idosellProduct
     */
    private function upsertLunarProduct(array $idosellProduct, ?int $existingProductId): Product
    {
        $product = $existingProductId !== null
            ? Product::find($existingProductId) ?? new Product
            : new Product;

        // Idosell returns translations keyed by language code in the
        // `translations` element. Pick the configured language.
        $language = (string) config('lunar-idosell.language', 'pl');
        $translation = (array) ($idosellProduct['translations'][$language] ?? []);

        $name = (string) ($translation['name'] ?? $idosellProduct['name'] ?? 'Untitled product');
        $description = (string) ($translation['long_description'] ?? '');

        $product->forceFill([
            'attribute_data' => array_merge(
                (array) $product->attribute_data,
                [
                    'name' => $name,
                    'description' => $description,
                ],
            ),
            'status' => 'published',
        ])->save();

        return $product;
    }

    /**
     * @param array<int, array<string, mixed>> $sizes Idosell `sizes` array.
     */
    private function upsertVariants(Product $product, array $sizes): void
    {
        foreach ($sizes as $size) {
            $sizeCode = (string) ($size['code'] ?? $size['id'] ?? '');
            if ($sizeCode === '') {
                continue;
            }

            $existingLink = IdosellLink::query()
                ->where('entity_type', IdosellEntityType::Variant->value)
                ->where('idosell_id', $sizeCode)
                ->first();

            $variant = $existingLink !== null
                ? ProductVariant::find($existingLink->entity_id) ?? $this->createVariant($product, $size, $sizeCode)
                : $this->createVariant($product, $size, $sizeCode);

            IdosellLink::query()->updateOrCreate(
                [
                    'entity_type' => IdosellEntityType::Variant->value,
                    'entity_id' => $variant->getKey(),
                ],
                [
                    'idosell_id' => $sizeCode,
                    'last_status' => IdosellLinkStatus::Success,
                    'last_synced_at' => now(),
                    'last_error' => null,
                    'last_payload_hash' => sha1(json_encode($size, JSON_THROW_ON_ERROR)),
                ],
            );
        }
    }

    /**
     * @param array<string, mixed> $size
     */
    private function createVariant(Product $product, array $size, string $sizeCode): ProductVariant
    {
        $variant = new ProductVariant;
        $variant->product_id = $product->getKey();
        $variant->sku = (string) ($size['code_producer'] ?? $sizeCode);
        $variant->stock = (int) ($size['stocks']['stock_id_'.config('lunar-idosell.warehouse_id', 1)]['stock'] ?? 0);
        $variant->save();

        return $variant;
    }
}
