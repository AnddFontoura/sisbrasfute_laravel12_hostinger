<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtReservation extends Model
{
    use HasFactory;
    // NO SoftDeletes — the model's delete() is a true hard delete, so there is no
    // code path that can soft-delete-and-block a slot. See design §1.

    protected $fillable = [
        'court_id',
        'reservation_date',
        'start_time',
        'end_time',
        'price_cents',
        'customer_name',
        'description',
        'is_mensalista',
        'recurrence_group_id',
        'created_by',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'is_mensalista' => 'boolean',
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
