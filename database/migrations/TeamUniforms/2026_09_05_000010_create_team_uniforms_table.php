<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_uniforms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable(false);
            // Popular name of the shirt/uniform (e.g. "Camisa branca").
            $table->string('name', 100)->nullable(false);
            // Storage path of the uniform photo.
            $table->string('photo')->nullable();
            // Optional price in centavos, for a future team store.
            $table->unsignedInteger('price_cents')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('team_id')->references('id')->on('teams');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_uniforms');
    }
};
