<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable log + deduplication store for inbound gateway webhooks.
 *
 * Every webhook the app receives is recorded here BEFORE it is enqueued for
 * processing. The unique `dedup_key` (provider + charge reference + status)
 * means a replayed delivery is recognized and skipped, so a payment is never
 * processed twice and never silently lost: the raw payload is always persisted
 * for auditing/reprocessing even if downstream settlement later fails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            // Which gateway sent it (efi, inter, null).
            $table->string('provider');
            // The charge reference parsed from the payload (EFI txid / cobrança
            // id), when resolvable. Nullable because some early/invalid payloads
            // may not carry one.
            $table->string('charge_reference')->nullable();
            // Stable key used to collapse duplicate deliveries of the same
            // logical event. Unique so a re-delivery is rejected at the DB.
            $table->string('dedup_key')->unique();
            // received  -> stored, awaiting processing
            // processed -> settled successfully (idempotent no-op counts too)
            // failed    -> processing threw; kept for retry/inspection
            // ignored   -> recognized but no matching transaction / no-op
            $table->string('status')->default('received');
            // Raw payload for audit and reprocessing.
            $table->json('payload');
            // Last processing error, if any.
            $table->text('last_error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'charge_reference']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
