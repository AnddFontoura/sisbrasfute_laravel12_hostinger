<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamPlayerUniformNumber extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'team_player_uniform_numbers';

    protected $fillable = [
        'team_player_id',
        'team_uniform_id',
        'number',
    ];

    protected $casts = [
        'number' => 'integer',
    ];

    public function uniform(): BelongsTo
    {
        return $this->belongsTo(TeamUniform::class, 'team_uniform_id');
    }

    public function teamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class, 'team_player_id');
    }
}
