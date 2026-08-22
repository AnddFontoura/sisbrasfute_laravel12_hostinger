<?php

namespace App\Models;

use App\Enums\MatchType;
use App\Enums\MyTeamIs;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Matches extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = "matches";

    protected $fillable = [
        'created_by_team_id',
        'championship_id',
        'match_type',
        'my_team_is',
        'my_team_id',
        'enemy_team_id',
        'field_id',
        'city_id',
        'championship_name',
        'my_team_name',
        'enemy_team_name',
        'my_team_score',
        'enemy_team_score',
        'has_penalties',
        'my_team_penalty_score',
        'enemy_team_penalty_score',
        'location',
        'schedule',
        'tag_id',
        'status',
    ];

    protected $casts = [
        'match_type' => MatchType::class,
        'my_team_is' => MyTeamIs::class,
        'schedule' => 'datetime',
    ];

    protected $appends = [
        'schedule_br',
        'match_type_label',
        'my_team_is_label',
    ];

    public function myTeamInfo(): HasOne
    {
        return $this->hasOne(Team::class, 'id', 'my_team_id');
    }

    public function enemyTeamInfo(): HasOne
    {
        return $this->hasOne(Team::class, 'id', 'enemy_team_id');
    }

    public function cityInfo(): HasOne
    {
        return $this->hasOne(City::class, 'id', 'city_id');
    }

    public function getScheduleBrAttribute(): ?string
    {
        return Carbon::create($this->schedule)->format('d/m/Y H:i');
    }

    public function getMatchTypeLabelAttribute(): string
    {
        return $this->match_type?->label() ?? '';
    }

    public function getMyTeamIsLabelAttribute(): string
    {
        return $this->my_team_is?->label() ?? '';
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(TeamTag::class, 'tag_id');
    }

    public function positions()
    {
        return $this->hasMany(MatchesHasGamePositions::class, 'match_id', 'id');
    }
}
