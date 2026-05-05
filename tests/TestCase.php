<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use WizcodePl\LunarIdosell\LunarIdosellServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
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
    }
}
