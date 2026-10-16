<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a DB-level uniqueness guarantee on the gateway charge reference.
 *
 * A single gateway charge (EFI txid / cobrança id) must map to exactly one
 * wallet transaction. This is the root defense against double-crediting when a
 * provider re-delivers the same webhook: even under a race, the second settle
 * attempt that tries to create/alter a conflicting row cannot violate this.
 *
 * MySQL allows multiple NULLs under a UNIQUE index, so synchronous/internal
 * transactions that carry no gateway_reference are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unique('gateway_reference', 'wallet_transactions_gateway_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('wallet_transactions_gateway_reference_unique');
        });
    }
};
