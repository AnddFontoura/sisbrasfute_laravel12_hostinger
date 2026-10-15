<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('court_id');
            $table->string('email', 254);
            $table->string('role', 20);
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('invited_by');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable(true);
            $table->unsignedBigInteger('accepted_by')->nullable(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['email', 'status']);

            $table->foreign('court_id')->references('id')->on('courts');
            $table->foreign('invited_by')->references('id')->on('users');
            $table->foreign('accepted_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_invitations');
    }
};
