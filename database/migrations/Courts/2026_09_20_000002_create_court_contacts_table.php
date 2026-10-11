<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('court_id');
            $table->string('type', 20);
            $table->string('value', 255);
            $table->string('label', 100)->nullable(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('court_id')->references('id')->on('courts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_contacts');
    }
};
