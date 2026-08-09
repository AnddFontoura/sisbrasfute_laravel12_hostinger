<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_player_has_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_player_id');
            $table->unsignedBigInteger('team_tag_id');
            $table->timestamps();

            $table->foreign('team_player_id')->references('id')->on('team_players');
            $table->foreign('team_tag_id')->references('id')->on('team_tags');

            $table->unique(['team_player_id', 'team_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_player_has_tags');
    }
};
