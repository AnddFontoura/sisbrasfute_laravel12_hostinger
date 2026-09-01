<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'notifications';

    protected $fillable = [
        'type',
        'title',
        'description',
        'team_id',
        'match_id',
        'created_by',
        'sent_by_email',
    ];

    protected $casts = [
        'sent_by_email' => 'boolean',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationUser::class, 'notification_id', 'id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_user', 'notification_id', 'user_id')
            ->withPivot(['read_at'])
            ->withTimestamps();
    }

    public function teamInfo(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function matchInfo(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
