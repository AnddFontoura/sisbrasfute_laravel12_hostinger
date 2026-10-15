<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('court_id');
            $table->date('reservation_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('price_cents');
            $table->string('customer_name', 254);
            $table->text('description')->nullable();
            $table->boolean('is_mensalista')->default(false);
            $table->char('recurrence_group_id', 36)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            // NO softDeletes() — cancel hard-deletes so the UNIQUE guard is always
            // correct and no hidden row can block a slot the UI shows as free.

            $table->unique(['court_id', 'reservation_date', 'start_time']);
            $table->index(['court_id', 'reservation_date']);
            // Intentionally NON-unique: a mensalista group shares one uuid across N rows.
            $table->index('recurrence_group_id');

            $table->foreign('court_id')->references('id')->on('courts');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_reservations');
    }
};
