<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->prefix.'idosell_links', function (Blueprint $table) {
            $table->id();

            // Polymorphic link to a Lunar entity (Product / ProductVariant /
            // Order). We don't enforce FK at the DB layer because polymorphic
            // FKs aren't expressible in standard SQL — a model observer
            // cleans up orphans on delete.
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');

            // Idosell-side identifier. String because Idosell mixes types:
            // numeric for productId, alphanumeric "size code" for variants,
            // alphanumeric for orderId.
            $table->string('idosell_id', 100);

            // Sync state of the LAST attempt (overwritten on each sync).
            $table->string('last_status', 16);
            $table->timestamp('last_synced_at');
            $table->text('last_error')->nullable();

            // Hash of the last payload we sent / received. Lets us skip
            // updates when nothing has changed since last successful sync.
            $table->string('last_payload_hash', 40)->nullable();

            // Per-entity extras that don't deserve their own column.
            // Example: orders carry `last_pushed_status` here.
            $table->json('meta')->nullable();

            $table->timestamps();

            // One link per Lunar entity; upserts work via this constraint.
            $table->unique(['entity_type', 'entity_id']);
            // Reverse lookup: find Lunar entity by Idosell id.
            $table->index('idosell_id');
            // Operational queries like "show me last 50 failures".
            $table->index(['last_status', 'last_synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'idosell_links');
    }
};
