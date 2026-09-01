<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotificationUser extends Model
{
    protected $table = 'notification_user';

    protected $fillable = [
        'notification_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }

    public function notificationInfo(): HasOne
    {
        return $this->hasOne(Notification::class, 'id', 'notification_id');
    }

    public function userInfo(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
