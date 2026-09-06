<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_has_players', function (Blueprint $table) {
            // Links the assignment to the specific position slot
            // (matches_has_game_positions row), which carries the team_id/side.
            // Nullable for backward compatibility with existing rows.
            $table->unsignedBigInteger('match_has_game_position_id')
                ->nullable()
                ->after('game_position_id');

            $table->index('match_has_game_position_id');
        });
    }

    public function down(): void
    {
        Schema::table('match_has_players', function (Blueprint $table) {
            $table->dropIndex(['match_has_game_position_id']);
            $table->dropColumn('match_has_game_position_id');
        });
    }
};
