<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_finance_reasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('name', 100)->nullable(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('team_id')->references('id')->on('teams');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_finance_reasons');
    }
};
