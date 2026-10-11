<?php

namespace App\Models;

use App\Enums\CourtInvitationStatus;
use App\Enums\CourtRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtInvitation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'court_id',
        'email',
        'role',
        'token',
        'status',
        'invited_by',
        'expires_at',
        'accepted_at',
        'accepted_by',
    ];

    protected $casts = [
        'role' => CourtRole::class,
        'status' => CourtInvitationStatus::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function courtInfo(): HasOne
    {
        return $this->hasOne(Court::class, 'id', 'court_id');
    }
}
