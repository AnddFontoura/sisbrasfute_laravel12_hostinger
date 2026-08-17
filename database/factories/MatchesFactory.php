<?php

namespace Database\Factories;

use App\Enums\MatchType;
use App\Enums\MyTeamIs;
use App\Models\City;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Matches>
 */
class MatchesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $cityId = City::inRandomOrder()->first();
        $myTeam = Team::inRandomOrder()->first();
        do {
            $enemyTeam = Team::inRandomOrder()->first();
        } while ($myTeam->id == $enemyTeam->id);

        return [
            'created_by_team_id' => $myTeam->id,
            'match_type' => $this->faker->randomElement(MatchType::cases())->value,
            'my_team_is' => $this->faker->randomElement(MyTeamIs::cases())->value,
            'championship_id' => null,
            'my_team_id' => $myTeam->id,
            'enemy_team_id' => $enemyTeam->id,
            'field_id' => null,
            'city_id' => $cityId,
            'championship_name' => null,
            'my_team_name' => $myTeam->name,
            'enemy_team_name' => $enemyTeam->name,
            'my_team_score' => rand(0, 10),
            'enemy_team_score' => rand(0, 10),
            'location' => $this->faker->address(),
            'schedule' => $this->faker->date(),
        ];
    }
}
