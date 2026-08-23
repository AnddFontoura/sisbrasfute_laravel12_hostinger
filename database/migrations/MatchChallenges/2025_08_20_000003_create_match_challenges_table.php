<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('challenger_team_id')->constrained('teams')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->tinyInteger('status')->default(0)->comment('0=pending, 1=host_accepted, 2=confirmed, 3=declined, 4=cancelled');
            $table->dateTime('host_confirmed_at')->nullable();
            $table->dateTime('challenger_confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['match_id', 'status']);
            $table->index(['challenger_team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_challenges');
    }
};
