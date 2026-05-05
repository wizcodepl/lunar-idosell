<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Tests\Feature;

use WizcodePl\LunarIdosell\LunarIdosellServiceProvider;
use WizcodePl\LunarIdosell\Tests\TestCase;

class PackageBootsTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->getProviders(LunarIdosellServiceProvider::class) !== [],
            'LunarIdosellServiceProvider should be registered by Orchestra Testbench',
        );
    }
}
