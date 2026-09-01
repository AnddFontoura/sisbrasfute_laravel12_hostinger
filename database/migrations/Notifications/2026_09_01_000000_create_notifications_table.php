<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['game', 'team', 'system'])->default('system');
            $table->string('title', 254)->nullable(false);
            $table->text('description')->nullable(false);
            $table->unsignedBigInteger('team_id')->nullable(true);
            $table->unsignedBigInteger('match_id')->nullable(true);
            $table->unsignedBigInteger('created_by')->nullable(true);
            $table->boolean('sent_by_email')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('match_id')->references('id')->on('matches')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
