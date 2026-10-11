<?php

namespace App\Models;

use App\Enums\ReservationDuration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtScheduleConfig extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'court_id',
        'weekday',
        'start_time',
        'end_time',
        'reservation_duration',
        'is_active',
    ];

    protected $casts = [
        'reservation_duration' => ReservationDuration::class,
        'is_active' => 'boolean',
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
