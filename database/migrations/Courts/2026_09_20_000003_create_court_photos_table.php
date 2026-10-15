<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('court_id');
            $table->string('photo_path', 1000);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('court_id')->references('id')->on('courts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_photos');
    }
};
