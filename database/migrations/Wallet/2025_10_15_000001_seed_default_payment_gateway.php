<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Default the active payment gateway to EFI. Uses updateOrInsert so it
        // is safe to run even if the key was already set manually.
        $exists = DB::table('system_configs')->where('key', 'payment_gateway')->exists();

        if (!$exists) {
            DB::table('system_configs')->insert([
                'key' => 'payment_gateway',
                'value' => 'efi',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('system_configs')->where('key', 'payment_gateway')->delete();
    }
};
