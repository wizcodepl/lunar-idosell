<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell;

use Illuminate\Support\ServiceProvider;

/**
 * Boots the lunar-idosell package — config, Idosell API client, sync
 * commands, queue jobs. Currently a scaffold; the actual integration
 * is being added incrementally.
 */
class LunarIdosellServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
