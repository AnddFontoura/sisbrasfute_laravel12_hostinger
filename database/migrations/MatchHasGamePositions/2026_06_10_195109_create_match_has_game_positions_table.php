<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches_has_game_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->nullable(true);
            $table->foreignId('game_position_id')->constrained('game_positions')->nullable(true);
            $table->foreignId('team_id')->constrained('teams')->nullable(true);
            $table->foreignId('player_id')->constrained('players')->nullable(true);
            $table->integer('team_reference')->nullable(true);
            $table->float('value', 2)->nullable(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches_has_game_positions');
    }
};
