<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Tests\Feature;

use WizcodePl\LunarIdosell\Actions\MapLunarStatusToIdosell;
use WizcodePl\LunarIdosell\Enums\IdosellOrderStatus;
use WizcodePl\LunarIdosell\Tests\TestCase;

/**
 * Pure unit test — no DB, no HTTP. Only the Lunar→Idosell status mapping.
 */
class MapLunarStatusToIdosellTest extends TestCase
{
    public function test_maps_known_lunar_statuses(): void
    {
        $map = new MapLunarStatusToIdosell;

        $this->assertSame(IdosellOrderStatus::New, $map('awaiting-payment'));
        $this->assertSame(IdosellOrderStatus::Paid, $map('paid'));
        $this->assertSame(IdosellOrderStatus::Cancelled, $map('cancelled'));
        $this->assertSame(IdosellOrderStatus::Cancelled, $map('refunded'));
    }

    public function test_returns_null_for_unknown_status(): void
    {
        $map = new MapLunarStatusToIdosell;

        $this->assertNull($map('shipped'));
        $this->assertNull($map(''));
        $this->assertNull($map('something-weird'));
    }
}
