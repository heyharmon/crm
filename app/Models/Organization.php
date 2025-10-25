<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OrganizationCategory;
use App\Models\OrganizationWebsiteRating;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'google_place_id',
        'banner',
        'score',
        'reviews',
        'street',
        'city',
        'state',
        'country_code',
        'website',
        'phone',
        'organization_category_id',
        'map_url',
        'notes',
    ];

    protected $casts = [
        'score' => 'decimal:1',
        'reviews' => 'integer',
        'website_rating_average' => 'float',
        'website_rating_count' => 'integer',
        'website_rating_weighted' => 'float',
    ];

    public function getFullAddressAttribute()
    {
        $parts = array_filter([$this->street, $this->city, $this->state]);
        return implode(', ', $parts);
    }

    // public function getFormattedWebsiteAttribute()
    // {
    //     if (!$this->website) return null;
    //     if (!str_starts_with($this->website, 'http')) {
    //         return 'https://' . $this->website;
    //     }
    //     return $this->website;
    // }

    public static function findByGooglePlaceId($placeId)
    {
        return static::withTrashed()->where('google_place_id', $placeId)->first();
    }

    public function scopeByLocation($query, $city = null, $state = null)
    {
        if ($city) {
            $query->where('organizations.city', 'LIKE', "%{$city}%");
        }
        if ($state) {
            $query->where('organizations.state', 'LIKE', "%{$state}%");
        }
        return $query;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(OrganizationCategory::class, 'organization_category_id');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->whereHas('category', function ($q) use ($category) {
            $q->where('name', 'LIKE', "%{$category}%");
        });
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function websiteRatings(): HasMany
    {
        return $this->hasMany(OrganizationWebsiteRating::class);
    }
}
