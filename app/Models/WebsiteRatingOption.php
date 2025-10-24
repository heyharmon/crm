<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteRatingOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'score',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'score' => 'integer',
    ];

    public function ratings(): HasMany
    {
        return $this->hasMany(OrganizationWebsiteRating::class);
    }
}

