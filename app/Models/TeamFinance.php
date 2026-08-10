<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamFinance extends Model
{
    //use HasFactory;
    use SoftDeletes;

    public $table = 'team_finances';

    public $fillable = [
        'team_id',
        'match_id',
        'team_player_id',
        'description',
        'value',
        'method',
        'type',
        'origin',
        'reason_id',
    ];

    public function teamInfo(): HasOne
    {
        return $this->hasOne(Team::class, 'id', 'team_id');
    }

    public function teamPlayerInfo(): HasOne
    {
        return $this->hasOne(TeamPlayer::class, 'id', 'team_player_id');
    }

    public function matchInfo(): HasOne
    {
        return $this->hasOne(Matches::class, 'id', 'match_id');
    }

    public function reasonInfo(): HasOne
    {
        return $this->hasOne(TeamFinanceReason::class, 'id', 'reason_id');
    }
}
