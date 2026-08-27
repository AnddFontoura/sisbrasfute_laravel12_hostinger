<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->boolean('notify_match')
                ->nullable(false)
                ->default(true)
                ->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->dropColumn('notify_match');
        });
    }
};
