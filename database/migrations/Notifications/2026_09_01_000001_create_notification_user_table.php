<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notification_id')->nullable(false);
            $table->unsignedBigInteger('user_id')->nullable(false);
            $table->timestamp('read_at')->nullable(true);
            $table->timestamps();

            $table->foreign('notification_id')->references('id')->on('notifications')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['notification_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_user');
    }
};
