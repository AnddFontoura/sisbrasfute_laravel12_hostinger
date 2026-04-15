<?php

namespace Database\Seeders\Factory;

use App\Models\TeamPlayer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeamPlayerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TeamPlayer::factory(100)->create();
    }
}
