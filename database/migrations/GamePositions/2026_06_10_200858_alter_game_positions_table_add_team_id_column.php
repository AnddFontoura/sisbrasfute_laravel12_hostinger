<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_positions', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained('teams')->nullable(true)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('game_positions', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};
