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
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function ratings(): HasMany
    {
        return $this->hasMany(OrganizationWebsiteRating::class);
    }
}
