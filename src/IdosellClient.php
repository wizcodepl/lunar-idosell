<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Idosell REST API (`/api/admin/v6/...`).
 *
 * Auth: `X-API-KEY` header. The header is shared across all endpoints,
 * which is why we don't keep a session token like tpay/payu.
 *
 * Idosell rate-limits aggressively (~25 req/s default per shop). On a 429
 * the underlying `Http::retry()` waits per the `Retry-After` header.
 */
class IdosellClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $apiKey = null,
    ) {}

    /**
     * Search products by shop. Single page; caller paginates by passing
     * `resultsPage` in the params.
     *
     * Endpoint: `POST /api/admin/v6/products/products/search`
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function searchProducts(array $params): array
    {
        return $this->post('/api/admin/v6/products/products/search', $params);
    }

    /**
     * Fetch full product detail (descriptions, images, prices) for a list
     * of Idosell product IDs.
     *
     * Endpoint: `POST /api/admin/v6/products/products/get`
     *
     * @param list<int|string> $idosellProductIds
     * @return array<string, mixed>
     */
    public function getProducts(array $idosellProductIds): array
    {
        return $this->post('/api/admin/v6/products/products/get', [
            'params' => [
                'productIdentType' => 'id',
                'returnProducts' => 'all',
                'returnElements' => [
                    'shops_prices',
                    'descriptions',
                    'images',
                    'sizes',
                    'stocks',
                    'category',
                    'producer',
                    'code_producer',
                    'translations',
                ],
                'productIds' => array_values($idosellProductIds),
            ],
        ]);
    }

    /**
     * Create an order in Idosell.
     *
     * Endpoint: `POST /api/admin/v6/orders/orders`
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        return $this->post('/api/admin/v6/orders/orders', $payload);
    }

    /**
     * Update an order's status.
     *
     * Endpoint: `PUT /api/admin/v6/orders/orders/status`
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateOrderStatus(array $payload): array
    {
        return $this->put('/api/admin/v6/orders/orders/status', $payload);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        return $this->decode(
            $this->http()->post($this->baseUrl().$path, $body),
            'POST '.$path,
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function put(string $path, array $body): array
    {
        return $this->decode(
            $this->http()->put($this->baseUrl().$path, $body),
            'PUT '.$path,
        );
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'X-API-KEY' => $this->apiKey(),
            'Accept' => 'application/json',
        ])
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            // 429 / 5xx → retry up to 3× with a short backoff. Idosell honors
            // `Retry-After` for explicit ratelimit responses.
            ->retry(3, 500, fn (\Throwable $e, $request) => true);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $context): array
    {
        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Idosell %s failed: HTTP %d — %s',
                $context,
                $response->status(),
                (string) $response->body(),
            ));
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException(sprintf(
                'Idosell %s returned non-JSON response',
                $context,
            ));
        }

        return $body;
    }

    private function baseUrl(): string
    {
        $url = (string) ($this->baseUrl ?? config('lunar-idosell.api_base_url'));
        if ($url === '') {
            throw new RuntimeException('Idosell `api_base_url` is not configured. Set IDOSELL_API_BASE_URL.');
        }

        return rtrim($url, '/');
    }

    private function apiKey(): string
    {
        $key = (string) ($this->apiKey ?? config('lunar-idosell.api_key'));
        if ($key === '') {
            throw new RuntimeException('Idosell `api_key` is not configured. Set IDOSELL_API_KEY.');
        }

        return $key;
    }
}
