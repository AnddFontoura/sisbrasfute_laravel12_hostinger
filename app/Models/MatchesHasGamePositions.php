<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MatchesHasGamePositions extends Model
{
    protected $table = 'matches_has_game_positions';

    protected $fillable = [
        'match_id',
        'game_position_id',
        'team_id',
        'player_id',
        'team_reference',
        'value'
    ];

    public function matchInfo(): HasOne
    {
        return $this->hasOne(Matches::class, 'id', 'match_id');
    }

    public function gamePositionInfo(): HasOne
    {
        return $this->hasOne(GamePosition::class, 'id', 'game_position_id');
    }
    
    public function teamInfo(): HasOne
    {
        return $this->hasOne(Team::class, 'id', 'team_id');
    }

    public function playerInfo(): HasOne
    {
        return $this->hasOne(Player::class, 'id', 'player_id');
    }
}
