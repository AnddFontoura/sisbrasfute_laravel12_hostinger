<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Number of internal sub-teams for an intra-team match
            // (match_type = Normal / "Partida entre o time"). For every
            // other match type this stays 1. Drives how many position
            // lists are generated and rendered.
            $table->unsignedTinyInteger('teams_count')->default(1)->after('enemy_team_id');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('teams_count');
        });
    }
};
