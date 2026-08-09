<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable(false);
            $table->string('name', 50)->nullable(false);
            $table->string('color', 7)->nullable(false)->default('#6b7280');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('team_id')->references('id')->on('teams');

            // Unique composite index on (team_id, name) to enforce uniqueness per team.
            // Note: MySQL doesn't support partial unique indexes (WHERE deleted_at IS NULL),
            // so uniqueness for soft-deleted records is handled at the application level.
            $table->unique(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_tags');
    }
};
