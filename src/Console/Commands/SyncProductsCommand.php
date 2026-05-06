<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Console\Commands;

use Illuminate\Console\Command;
use WizcodePl\LunarIdosell\Actions\PullProductsFromIdosell;

/**
 * Run on a cron / by hand:
 *   php artisan idosell:sync-products
 *
 * Pulls products + variants for the configured shop and warehouse.
 * Idempotent — safe to run as often as you like.
 */
class SyncProductsCommand extends Command
{
    protected $signature = 'idosell:sync-products';

    protected $description = 'Pull products from Idosell into Lunar (idempotent).';

    public function handle(PullProductsFromIdosell $pull): int
    {
        $this->info('Pulling products from Idosell …');
        $stats = $pull();

        $this->table(
            ['Processed', 'Succeeded', 'Failed'],
            [[$stats['processed'], $stats['succeeded'], $stats['failed']]],
        );

        return $stats['failed'] === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
