<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Throwable;
use WizcodePl\LunarIdosell\Enums\IdosellEntityType;
use WizcodePl\LunarIdosell\Enums\IdosellLinkStatus;
use WizcodePl\LunarIdosell\Events\IdosellOrderPushed;
use WizcodePl\LunarIdosell\Events\IdosellOrderPushFailed;
use WizcodePl\LunarIdosell\IdosellClient;
use WizcodePl\LunarIdosell\Models\IdosellLink;

/**
 * Pushes a Lunar Order to Idosell as a new order. Idempotent: if a link
 * row already exists for this Lunar Order, we **don't** create another
 * Idosell order — status updates flow through `UpdateOrderStatusInIdosell`.
 *
 * Customer is implicit: we send the buyer's email and Idosell creates /
 * matches the client record on its side.
 *
 * Variant mapping is mandatory — every order line item must reference a
 * Lunar variant that already has an Idosell link. If any line item is
 * unmapped, the whole push fails (we don't want to create a half-order).
 */
class PushOrderToIdosell
{
    public function __construct(
        private readonly IdosellClient $client,
        private readonly MapLunarStatusToIdosell $statusMapper,
    ) {}

    public function __invoke(Order $order): IdosellLink
    {
        $existing = IdosellLink::findFor($order);
        if ($existing !== null && $existing->idosell_id !== '' && $existing->last_status === IdosellLinkStatus::Success) {
            // Already pushed once — let the status updater handle changes.
            return $existing;
        }

        try {
            $payload = $this->buildPayload($order);

            $response = $this->client->createOrder($payload);
            $idosellOrderId = (string) ($response['order']['id'] ?? $response['result']['orderId'] ?? '');

            if ($idosellOrderId === '') {
                throw new \RuntimeException('Idosell createOrder returned no order id');
            }

            $link = DB::transaction(fn () => IdosellLink::query()->updateOrCreate(
                [
                    'entity_type' => IdosellEntityType::Order->value,
                    'entity_id' => $order->getKey(),
                ],
                [
                    'idosell_id' => $idosellOrderId,
                    'last_status' => IdosellLinkStatus::Success,
                    'last_synced_at' => now(),
                    'last_error' => null,
                    'last_payload_hash' => sha1(json_encode($payload, JSON_THROW_ON_ERROR)),
                    'meta' => [
                        'last_pushed_status' => $this->statusMapper->__invoke((string) $order->status)?->value,
                    ],
                ],
            ));

            IdosellOrderPushed::dispatch($order, $link);

            return $link;
        } catch (Throwable $e) {
            $link = IdosellLink::query()->updateOrCreate(
                [
                    'entity_type' => IdosellEntityType::Order->value,
                    'entity_id' => $order->getKey(),
                ],
                [
                    'idosell_id' => $existing?->idosell_id ?? '',
                    'last_status' => IdosellLinkStatus::Failed,
                    'last_synced_at' => now(),
                    'last_error' => $e->getMessage(),
                ],
            );

            Log::channel((string) config('lunar-idosell.log_channel', 'stack'))->error(
                'lunar-idosell | failed to push order',
                ['order_id' => $order->getKey(), 'error' => $e->getMessage()],
            );

            IdosellOrderPushFailed::dispatch($order, $link, $e->getMessage());

            return $link;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Order $order): array
    {
        $billing = $order->billingAddress;

        return [
            'params' => [
                'orders' => [
                    [
                        'orderSettings' => [
                            'shopId' => (int) config('lunar-idosell.shop_id', 1),
                        ],
                        'orderDetails' => [
                            'payments' => [
                                'orderPaymentType' => 'cash_on_delivery',
                            ],
                            'productsResults' => $this->mapLineItems($order),
                            'orderDeliveryCostsPayer' => 'client',
                        ],
                        'clients' => [
                            'clientLogin' => (string) ($billing?->contact_email ?? ''),
                            'clientFirstName' => (string) ($billing?->first_name ?? ''),
                            'clientLastName' => (string) ($billing?->last_name ?? ''),
                            'clientStreet' => (string) ($billing?->line_one ?? ''),
                            'clientZipCode' => (string) ($billing?->postcode ?? ''),
                            'clientCity' => (string) ($billing?->city ?? ''),
                            'clientCountry' => (string) ($billing?->country?->iso2 ?? 'PL'),
                            'clientPhone1' => (string) ($billing?->contact_phone ?? ''),
                            'clientEmail' => (string) ($billing?->contact_email ?? ''),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapLineItems(Order $order): array
    {
        $items = [];
        foreach ($order->lines as $line) {
            $variant = $line->purchasable;
            $link = $variant !== null ? IdosellLink::findFor($variant) : null;

            if ($link === null) {
                throw new \RuntimeException(sprintf(
                    'Line %d on order %d has no Idosell link — cannot push partial order',
                    $line->id ?? 0,
                    $order->getKey(),
                ));
            }

            $items[] = [
                'productId' => $link->idosell_id,
                'productSizeId' => $link->idosell_id,
                'productQuantity' => (int) $line->quantity,
                // Price in major units, format Idosell expects.
                'productOrderPrice' => number_format(((int) $line->total->value) / 100, 2, '.', ''),
            ];
        }

        return $items;
    }
}
