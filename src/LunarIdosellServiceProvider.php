<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Order;
use WizcodePl\LunarIdosell\Console\Commands\SyncProductsCommand;
use WizcodePl\LunarIdosell\Observers\OrderObserver;

class LunarIdosellServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lunar-idosell.php', 'lunar-idosell');

        $this->app->singleton(IdosellClient::class, fn () => new IdosellClient);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncProductsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/lunar-idosell.php' => config_path('lunar-idosell.php'),
            ], 'lunar-idosell-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'lunar-idosell-migrations');
        }

        // Lunar → Idosell — order push on creation + status sync on update.
        // Lunar 1.3 doesn't fire its own `OrderPlaced` event, so the
        // observer's `created` hook stands in for it.
        Order::observe(OrderObserver::class);
    }
}
