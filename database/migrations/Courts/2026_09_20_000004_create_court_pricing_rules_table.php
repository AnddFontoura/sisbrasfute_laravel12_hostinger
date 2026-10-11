<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('court_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('price_cents');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('court_id')->references('id')->on('courts');
            $table->index(['court_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_pricing_rules');
    }
};
