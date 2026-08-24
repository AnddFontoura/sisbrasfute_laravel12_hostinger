<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_receivables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('match_id')->nullable();
            $table->integer('amount_cents');
            $table->string('status')->default('pending'); // pending, withdrawn
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->foreign('team_id')->references('id')->on('teams');
            $table->foreign('match_id')->references('id')->on('matches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_receivables');
    }
};
