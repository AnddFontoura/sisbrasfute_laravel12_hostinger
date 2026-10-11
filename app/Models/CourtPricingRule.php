<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtPricingRule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'court_id',
        'weekday',
        'start_time',
        'end_time',
        'price_cents',
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
