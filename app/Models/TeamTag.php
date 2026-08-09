<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamTag extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'team_tags';

    protected $fillable = ['team_id', 'name', 'color'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function teamPlayers(): BelongsToMany
    {
        return $this->belongsToMany(TeamPlayer::class, 'team_player_has_tags', 'team_tag_id', 'team_player_id')
            ->withTimestamps();
    }
}
