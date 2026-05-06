<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Actions;

use Illuminate\Support\Facades\Log;
use Throwable;
use WizcodePl\LunarIdosell\IdosellClient;

/**
 * Bulk pulls products from Idosell into Lunar. One synchronous run; safe
 * to call repeatedly (idempotent via `UpsertProductFromIdosell`).
 *
 * Pagination strategy: page through `searchProducts` to learn the IDs that
 * belong to our shop, then fetch each chunk in detail via `getProducts`,
 * and upsert one at a time.
 *
 * Error handling: each product upsert is wrapped in its own try/catch by
 * the upsert action. A single bad product doesn't kill the whole run.
 */
class PullProductsFromIdosell
{
    public function __construct(
        private readonly IdosellClient $client,
        private readonly UpsertProductFromIdosell $upsert,
    ) {}

    /**
     * @return array{processed: int, succeeded: int, failed: int}
     */
    public function __invoke(): array
    {
        $shopId = (int) config('lunar-idosell.shop_id', 1);
        $chunkSize = (int) config('lunar-idosell.sync.products_chunk_size', 100);
        $page = 1;
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        do {
            $response = $this->client->searchProducts([
                'params' => [
                    'returnProducts' => 'all',
                    'shopId' => $shopId,
                    'resultsPage' => $page,
                    'resultsLimit' => $chunkSize,
                ],
            ]);

            $idosellIds = collect((array) ($response['results'] ?? []))
                ->pluck('productId')
                ->filter()
                ->values()
                ->all();

            if ($idosellIds === []) {
                break;
            }

            $detail = $this->client->getProducts($idosellIds);

            foreach ((array) ($detail['results'] ?? []) as $idosellProduct) {
                $processed++;
                try {
                    $link = ($this->upsert)((array) $idosellProduct);
                    $link->last_status->value === 'success' ? $succeeded++ : $failed++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::channel((string) config('lunar-idosell.log_channel', 'stack'))->error(
                        'lunar-idosell | unexpected error during product upsert',
                        ['error' => $e->getMessage()],
                    );
                }
            }

            $totalPages = (int) ($response['resultsNumberPage'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return ['processed' => $processed, 'succeeded' => $succeeded, 'failed' => $failed];
    }
}
