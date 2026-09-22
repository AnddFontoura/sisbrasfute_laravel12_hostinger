<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class TeamUniform extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'team_uniforms';

    protected $fillable = [
        'team_id',
        'name',
        'photo',
        'price_cents',
    ];

    protected $appends = ['photo_url'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }

        return Storage::disk('public')->url($this->photo);
    }
}
