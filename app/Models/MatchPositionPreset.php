<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MatchPositionPreset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'match_position_presets';

    protected $fillable = [
        'team_id',
        'name',
        'positions',
        'teams_count',
    ];

    protected $casts = [
        'positions' => 'array',
        'teams_count' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
