<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_schedule_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('court_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time')->nullable(true);
            $table->time('end_time')->nullable(true);
            $table->unsignedSmallInteger('reservation_duration')->nullable(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('court_id')->references('id')->on('courts');
            $table->unique(['court_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_schedule_configs');
    }
};
