<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerMatchStatistic extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'player_match_statistics';

    protected $fillable = [
        'match_has_player_id',
        'goals_scored',
        'goals_conceded',
        'assists',
        'yellow_cards',
        'red_cards',
        'saves',
        'fouls_committed',
        'fouls_suffered',
    ];

    public function matchHasPlayer(): BelongsTo
    {
        return $this->belongsTo(MatchHasPlayer::class, 'match_has_player_id');
    }
}
