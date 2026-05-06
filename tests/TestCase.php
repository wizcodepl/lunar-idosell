<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Tests;

use Cartalyst\Converter\Laravel\ConverterServiceProvider;
use Kalnoy\Nestedset\NestedSetServiceProvider;
use Lunar\LunarServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelBlink\BlinkServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use WizcodePl\LunarIdosell\LunarIdosellServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ConverterServiceProvider::class,
            ActivitylogServiceProvider::class,
            MediaLibraryServiceProvider::class,
            BlinkServiceProvider::class,
            NestedSetServiceProvider::class,
            LunarServiceProvider::class,
            LunarIdosellServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('lunar-idosell.api_base_url', env('IDOSELL_API_BASE_URL', 'https://example.test'));
        $app['config']->set('lunar-idosell.api_key', env('IDOSELL_API_KEY', 'test-key'));
        $app['config']->set('lunar-idosell.shop_id', 1);
        $app['config']->set('lunar-idosell.warehouse_id', 1);
        $app['config']->set('lunar-idosell.lunar_channel_id', 1);

        // Disable observer-driven jobs by default. Tests that need to
        // exercise the observer can flip these on explicitly.
        $app['config']->set('lunar-idosell.sync.orders_on_placed', false);
        $app['config']->set('lunar-idosell.sync.orders_on_status_change', false);
    }

    protected function skipIfNoSandboxCreds(): void
    {
        if (! getenv('IDOSELL_API_BASE_URL') || ! getenv('IDOSELL_API_KEY')) {
            $this->markTestSkipped(
                'Set IDOSELL_API_BASE_URL and IDOSELL_API_KEY in your shell to run sandbox e2e tests.'
            );
        }
    }
}
