<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Lunar\Database\Factories\OrderFactory;
use Lunar\Models\Currency;
use PHPUnit\Framework\Attributes\Group;
use WizcodePl\LunarIdosell\Jobs\PushOrderToIdosellJob;
use WizcodePl\LunarIdosell\Jobs\UpdateOrderStatusInIdosellJob;
use WizcodePl\LunarIdosell\Tests\TestCase;

#[Group('e2e')]
class OrderObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::factory()->create([
            'code' => 'PLN',
            'default' => true,
            'enabled' => true,
            'exchange_rate' => 1,
            'decimal_places' => 2,
        ]);
    }

    public function test_dispatches_push_job_on_order_creation_when_enabled(): void
    {
        Config::set('lunar-idosell.sync.orders_on_placed', true);
        Bus::fake();

        OrderFactory::new()->create([
            'total' => 1500,
            'sub_total' => 1500,
            'currency_code' => 'PLN',
        ]);

        Bus::assertDispatched(PushOrderToIdosellJob::class);
    }

    public function test_does_not_dispatch_push_job_when_disabled(): void
    {
        Config::set('lunar-idosell.sync.orders_on_placed', false);
        Bus::fake();

        OrderFactory::new()->create([
            'total' => 1500,
            'sub_total' => 1500,
            'currency_code' => 'PLN',
        ]);

        Bus::assertNotDispatched(PushOrderToIdosellJob::class);
    }

    public function test_dispatches_status_job_only_when_status_changes(): void
    {
        Config::set('lunar-idosell.sync.orders_on_status_change', true);
        Bus::fake();

        $order = OrderFactory::new()->create([
            'total' => 1500,
            'sub_total' => 1500,
            'currency_code' => 'PLN',
            'status' => 'awaiting-payment',
        ]);

        // Touch a non-status field — observer should NOT dispatch.
        $order->update(['sub_total' => 1500]);
        Bus::assertNotDispatched(UpdateOrderStatusInIdosellJob::class);

        // Change status — observer SHOULD dispatch.
        $order->update(['status' => 'paid']);
        Bus::assertDispatched(UpdateOrderStatusInIdosellJob::class);
    }
}
