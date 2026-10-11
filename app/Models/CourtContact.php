<?php

namespace App\Models;

use App\Enums\CourtContactType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourtContact extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'court_id',
        'type',
        'value',
        'label',
        'position',
    ];

    protected $casts = [
        'type' => CourtContactType::class,
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
