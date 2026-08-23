<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MatchChallenge extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'match_challenges';

    // Status constants
    const STATUS_PENDING = 0;
    const STATUS_HOST_ACCEPTED = 1;
    const STATUS_CONFIRMED = 2;
    const STATUS_DECLINED = 3;
    const STATUS_CANCELLED = 4;

    protected $fillable = [
        'match_id',
        'challenger_team_id',
        'message',
        'status',
        'host_confirmed_at',
        'challenger_confirmed_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'host_confirmed_at' => 'datetime',
        'challenger_confirmed_at' => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function challengerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'challenger_team_id');
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isHostAccepted(): bool
    {
        return $this->status === self::STATUS_HOST_ACCEPTED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isDeclined(): bool
    {
        return $this->status === self::STATUS_DECLINED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
