<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_finances', function (Blueprint $table) {
            $table->unsignedBigInteger('reason_id')->nullable()->after('origin');
            $table->foreign('reason_id')->references('id')->on('team_finance_reasons');
        });
    }

    public function down(): void
    {
        Schema::table('team_finances', function (Blueprint $table) {
            $table->dropForeign(['reason_id']);
            $table->dropColumn('reason_id');
        });
    }
};
