<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->tinyInteger('challenge_status')->default(0)->after('status')
                ->comment('0=normal, 1=open_for_challenges, 2=challenge_confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('challenge_status');
        });
    }
};
