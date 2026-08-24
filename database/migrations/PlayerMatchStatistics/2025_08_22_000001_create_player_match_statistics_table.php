<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_match_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_has_player_id')
                ->unique()
                ->constrained('match_has_players')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('goals_scored')->default(0);
            $table->unsignedTinyInteger('goals_conceded')->default(0);
            $table->unsignedTinyInteger('assists')->default(0);
            $table->unsignedTinyInteger('yellow_cards')->default(0);
            $table->unsignedTinyInteger('red_cards')->default(0);
            $table->unsignedTinyInteger('saves')->default(0);
            $table->unsignedTinyInteger('fouls_committed')->default(0);
            $table->unsignedTinyInteger('fouls_suffered')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_match_statistics');
    }
};
