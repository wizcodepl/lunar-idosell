<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Database\Factories\OrderFactory;
use Lunar\Models\Currency;
use PHPUnit\Framework\Attributes\Group;
use WizcodePl\LunarIdosell\Enums\IdosellEntityType;
use WizcodePl\LunarIdosell\Enums\IdosellLinkStatus;
use WizcodePl\LunarIdosell\Models\IdosellLink;
use WizcodePl\LunarIdosell\Tests\TestCase;

/**
 * Confirms the model maps to the right table, casts work, and the
 * polymorphic helper resolves both ways.
 */
#[Group('e2e')]
class IdosellLinkTest extends TestCase
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

    public function test_persists_with_status_enum_cast(): void
    {
        $link = IdosellLink::query()->create([
            'entity_type' => IdosellEntityType::Order->value,
            'entity_id' => 99,
            'idosell_id' => 'ORD-1',
            'last_status' => IdosellLinkStatus::Success,
            'last_synced_at' => now(),
        ]);

        $fresh = IdosellLink::query()->find($link->id);

        $this->assertSame(IdosellLinkStatus::Success, $fresh->last_status);
        $this->assertSame('ORD-1', $fresh->idosell_id);
    }

    public function test_find_for_resolves_lunar_order(): void
    {
        $order = OrderFactory::new()->create([
            'total' => 1500,
            'sub_total' => 1500,
            'currency_code' => 'PLN',
        ]);

        IdosellLink::query()->create([
            'entity_type' => IdosellEntityType::Order->value,
            'entity_id' => $order->getKey(),
            'idosell_id' => 'ORD-99',
            'last_status' => IdosellLinkStatus::Success,
            'last_synced_at' => now(),
        ]);

        $link = IdosellLink::findFor($order);

        $this->assertNotNull($link);
        $this->assertSame('ORD-99', $link->idosell_id);
    }

    public function test_unique_constraint_on_entity(): void
    {
        IdosellLink::query()->create([
            'entity_type' => IdosellEntityType::Product->value,
            'entity_id' => 7,
            'idosell_id' => '111',
            'last_status' => IdosellLinkStatus::Success,
            'last_synced_at' => now(),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        IdosellLink::query()->create([
            'entity_type' => IdosellEntityType::Product->value,
            'entity_id' => 7,
            'idosell_id' => '222',
            'last_status' => IdosellLinkStatus::Success,
            'last_synced_at' => now(),
        ]);
    }

    public function test_meta_round_trips_as_array(): void
    {
        $link = IdosellLink::query()->create([
            'entity_type' => IdosellEntityType::Order->value,
            'entity_id' => 50,
            'idosell_id' => 'ORD-50',
            'last_status' => IdosellLinkStatus::Success,
            'last_synced_at' => now(),
            'meta' => ['last_pushed_status' => 'paid'],
        ]);

        $this->assertSame('paid', $link->fresh()->meta['last_pushed_status']);
    }
}
