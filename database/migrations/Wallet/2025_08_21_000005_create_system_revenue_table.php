<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_revenue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_transaction_id');
            $table->integer('amount_cents');
            $table->string('type')->default('match_fee');
            $table->timestamps();
            $table->foreign('wallet_transaction_id')->references('id')->on('wallet_transactions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_revenue');
    }
};
