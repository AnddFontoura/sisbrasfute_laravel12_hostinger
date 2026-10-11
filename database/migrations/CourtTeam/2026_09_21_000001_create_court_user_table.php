<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('court_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['court_id', 'user_id']);

            $table->foreign('court_id')->references('id')->on('courts');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_user');
    }
};
