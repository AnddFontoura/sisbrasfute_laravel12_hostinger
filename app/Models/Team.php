<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Team extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'city_id',
        'modality_id',
        'slug',
        'name',
        'description',
        'foundation_date',
        'logo_path',
        'banner_path',
        'gender',
        'allow_application',
        'social_profiles',
    ];

    protected $appends = [
        'banner_url',
        'logo_url',
        'foundation_date_br'
    ];

    protected $casts = [
        'social_profiles' => 'array',
    ];

    public function cityInfo(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function modalityInfo(): BelongsTo
    {
        return $this->belongsTo(Modality::class, 'id', 'modality_id');
    }
    public function getBannerUrlAttribute(): ?string
    {
        if (!$this->banner_path) {
            return null;
        }

        return Storage::disk('public')->url($this->banner_path);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function getFoundationDateBrAttribute(): ?string
    {
        return Carbon::create($this->fundation_date)->format('d/m/Y');
    }
}
