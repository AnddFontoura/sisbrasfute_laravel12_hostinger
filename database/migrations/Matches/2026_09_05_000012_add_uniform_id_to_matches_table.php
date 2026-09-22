<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Uniform (team shirt) used in this match; drives the number
            // suggestions when a player takes a position. Nullable FK.
            $table->unsignedBigInteger('uniform_id')->nullable()->after('tag_id');

            $table->foreign('uniform_id')->references('id')->on('team_uniforms');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['uniform_id']);
            $table->dropColumn('uniform_id');
        });
    }
};
