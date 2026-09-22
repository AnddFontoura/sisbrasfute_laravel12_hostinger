<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_player_uniform_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_player_id')->nullable(false);
            $table->unsignedBigInteger('team_uniform_id')->nullable(false);
            // A player may own several shirts (numbers) of the same uniform,
            // so multiple rows per (team_player_id, team_uniform_id) are allowed.
            $table->unsignedInteger('number')->nullable(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('team_player_id')->references('id')->on('team_players');
            $table->foreign('team_uniform_id')->references('id')->on('team_uniforms');

            $table->index(['team_player_id', 'team_uniform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_player_uniform_numbers');
    }
};
