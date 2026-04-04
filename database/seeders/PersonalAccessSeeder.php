<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersonalAccessSeeder extends Seeder
{
    protected $data = [
        [
            'name' => 'Laravel Personal Access Client',
            'secret' => '9yyP2dvVQEHPuPO46QoOWPvJ2CHZaRJpA6wymKaV',
            'redirect' => 'http://localhost',
            'provider' => null,
            'personal_access_client' => true,
            'password_client' => false,
            'revoked' => false,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ],
        [
            'name' => 'Laravel Password Grant Client',
            'secret' => '85zTk2bf3Qr15FOxxBNzwF5isBxcxKHMg2ryGGdd',
            'redirect' => 'http://localhost',
            'provider' => 'users',
            'personal_access_client' => false,
            'password_client' => true,
            'revoked' => false,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ],
    ];

    public function run(): void
    {
        if (env('APP_ENV') !== 'production') {
            foreach ($this->data as $acessKey) {
                DB::table('oauth_clients')->insert($acessKey);
            }

            DB::table('oauth_personal_access_clients')->insert([
                'id' => 1,
                'client_id' => 3,
            ]);
        }
    }
}
