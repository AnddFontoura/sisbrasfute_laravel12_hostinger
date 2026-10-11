<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('modality_id');
            $table->unsignedBigInteger('city_id');
            $table->string('slug', 500)->nullable(true);
            $table->string('name', 254);
            $table->text('description')->nullable(true);
            $table->string('address_line', 500)->nullable(true);
            $table->string('surface_type', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->string('rejection_reason', 1000)->nullable(true);
            $table->timestamp('approved_at')->nullable(true);
            $table->unsignedBigInteger('approved_by')->nullable(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('modality_id')->references('id')->on('modalities');
            $table->foreign('city_id')->references('id')->on('cities');
            $table->foreign('approved_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
