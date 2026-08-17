<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Drop old foreign keys
            $table->dropForeign(['visitor_team_id']);
            $table->dropForeign(['home_team_id']);

            // Rename home/visitor columns to my_team/enemy_team
            $table->renameColumn('home_team_id', 'my_team_id');
            $table->renameColumn('visitor_team_id', 'enemy_team_id');
            $table->renameColumn('home_team_name', 'my_team_name');
            $table->renameColumn('visitor_team_name', 'enemy_team_name');
            $table->renameColumn('home_score', 'my_team_score');
            $table->renameColumn('visitor_score', 'enemy_team_score');
            $table->renameColumn('home_penalty_score', 'my_team_penalty_score');
            $table->renameColumn('visitor_penalty_score', 'enemy_team_penalty_score');
        });

        Schema::table('matches', function (Blueprint $table) {
            // Re-add foreign keys with new column names
            $table->foreign('my_team_id')->references('id')->on('teams');
            $table->foreign('enemy_team_id')->references('id')->on('teams');

            // Add new columns
            $table->unsignedTinyInteger('match_type')->default(0)->after('created_by_team_id');
            $table->boolean('my_team_is')->default(0)->after('match_type');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['my_team_id']);
            $table->dropForeign(['enemy_team_id']);

            $table->dropColumn('match_type');
            $table->dropColumn('my_team_is');

            $table->renameColumn('my_team_id', 'home_team_id');
            $table->renameColumn('enemy_team_id', 'visitor_team_id');
            $table->renameColumn('my_team_name', 'home_team_name');
            $table->renameColumn('enemy_team_name', 'visitor_team_name');
            $table->renameColumn('my_team_score', 'home_score');
            $table->renameColumn('enemy_team_score', 'visitor_score');
            $table->renameColumn('my_team_penalty_score', 'home_penalty_score');
            $table->renameColumn('enemy_team_penalty_score', 'visitor_penalty_score');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->foreign('home_team_id')->references('id')->on('teams');
            $table->foreign('visitor_team_id')->references('id')->on('teams');
        });
    }
};
