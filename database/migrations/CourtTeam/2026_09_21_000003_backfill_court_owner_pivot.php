<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Insert an owner pivot row for every court that lacks an active owner
        // pivot. Uses the query builder (no Eloquent models) so it stays robust
        // against future model changes.
        DB::table('courts')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('court_user')
                    ->whereColumn('court_user.court_id', 'courts.id')
                    ->whereColumn('court_user.user_id', 'courts.user_id')
                    ->where('court_user.role', 'owner')
                    ->whereNull('court_user.deleted_at');
            })
            ->orderBy('id')
            ->select('id', 'user_id')
            ->chunk(500, function ($courts) use ($now) {
                $rows = [];

                foreach ($courts as $court) {
                    $rows[] = [
                        'court_id' => $court->id,
                        'user_id' => $court->user_id,
                        'role' => 'owner',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('court_user')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        DB::table('court_user')
            ->where('role', 'owner')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('courts')
                    ->whereColumn('courts.id', 'court_user.court_id')
                    ->whereColumn('courts.user_id', 'court_user.user_id');
            })
            ->delete();
    }
};
