<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamReceivable extends Model
{
    protected $table = 'team_receivables';

    protected $fillable = [
        'team_id',
        'match_id',
        'amount_cents',
        'status',
        'withdrawn_at',
    ];

    protected $casts = [
        'withdrawn_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }
}
