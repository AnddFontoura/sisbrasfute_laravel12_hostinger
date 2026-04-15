<?php

namespace Database\Factories;

use App\Enums\ShirtSizeEnum;
use App\Models\City;
use App\Models\GamePosition;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeamPlayer>
 */
class TeamPlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $userId = User::factory()->create()->id;
        $teamId = Team::inRandomOrder()->first()->id;
        $cityId = City::inRandomOrder()->first()->id;
        $gamePosition = GamePosition::inRandomOrder()->first()->id;
        $name = $this->faker->name();
        $uniform = ['M', 'G', 'F'];

        return [
            'user_id' => $userId,
            'team_id' => $teamId,
            //'city_id' => $cityId,
            'game_position_id' => $gamePosition,
            'name' => $name,
            'nickname' => Str::slug($name),
            'uniform_size' => $uniform[rand(0, sizeof($uniform) - 1)],
            'photo' => null,
            'height' => rand(60, 250),
            'weight' => rand(70, 120),
            'foot_size' => rand(30, 44),
            'glove_size' => rand(6, 12),
            'birthdate' => $this->faker->date(),
            //'status' => rand(0, 1)
        ];
    }
}
