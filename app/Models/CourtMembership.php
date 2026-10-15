<?php

namespace App\Models;

use App\Enums\CourtRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtMembership extends Model
{
    use SoftDeletes;

    protected $table = 'court_user';

    protected $fillable = [
        'court_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => CourtRole::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
