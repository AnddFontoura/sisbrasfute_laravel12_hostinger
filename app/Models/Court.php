<?php

namespace App\Models;

use App\Enums\CourtStatus;
use App\Enums\CourtSurfaceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Court extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'modality_id',
        'city_id',
        'slug',
        'name',
        'description',
        'address_line',
        'surface_type',
        'status',
        'rejection_reason',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'surface_type' => CourtSurfaceType::class,
        'status' => CourtStatus::class,
        'approved_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function modalityInfo(): BelongsTo
    {
        return $this->belongsTo(Modality::class, 'modality_id', 'id');
    }

    public function cityInfo(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CourtContact::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CourtPhoto::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(CourtPricingRule::class);
    }

    public function scheduleConfigs(): HasMany
    {
        return $this->hasMany(CourtScheduleConfig::class);
    }
}
