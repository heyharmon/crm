<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OrganizationCategory;
use App\Models\OrganizationWebsiteRating;
use App\Models\OrganizationWebsiteRedesign;
use Carbon\Carbon;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    public const WEBSITE_STATUS_UP = 'up';
    public const WEBSITE_STATUS_DOWN = 'down';
    public const WEBSITE_STATUS_REDIRECTED = 'redirected';
    public const WEBSITE_STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'name',
        'type',
        'google_place_id',
        'source',
        'banner',
        'score',
        'reviews',
        'street',
        'city',
        'state',
        'country',
        'website',
        'website_crawl_status',
        'website_crawl_message',
        'redirects_to',
        'cms',
        'website_status',
        'website_redesign_status',
        'website_redesign_status_message',
        'phone',
        'organization_category_id',
        'map_url',
        'notes',
        'charter_number',
        'is_low_income',
        'members',
        'assets',
        'loans',
        'deposits',
        'roaa',
        'net_worth_ratio',
        'loan_to_share_ratio',
        'deposit_growth',
        'loan_growth',
        'asset_growth',
        'member_growth',
        'net_worth_growth',
        'last_major_redesign_at',
        'last_major_redesign_at_actual',
    ];

    protected $casts = [
        'score' => 'decimal:1',
        'reviews' => 'integer',
        'website_rating_average' => 'float',
        'website_rating_count' => 'integer',
        'website_rating_weighted' => 'float',
        'last_major_redesign_at' => 'date',
        'last_major_redesign_at_actual' => 'date',
        'charter_number' => 'integer',
        'is_low_income' => 'boolean',
        'members' => 'integer',
        'assets' => 'integer',
        'loans' => 'integer',
        'deposits' => 'integer',
        'roaa' => 'decimal:2',
        'net_worth_ratio' => 'decimal:2',
        'loan_to_share_ratio' => 'decimal:2',
        'deposit_growth' => 'decimal:2',
        'loan_growth' => 'decimal:2',
        'asset_growth' => 'decimal:2',
        'member_growth' => 'decimal:2',
        'net_worth_growth' => 'decimal:2',
    ];

    protected $attributes = [
        'website_status' => self::WEBSITE_STATUS_UNKNOWN,
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

    public function websiteRedesigns(): HasMany
    {
        return $this->hasMany(OrganizationWebsiteRedesign::class)->orderByDesc('after_captured_at');
    }

    /**
     * Get the value of date-only fields as date strings (YYYY-MM-DD) to avoid timezone issues.
     */
    public function getLastMajorRedesignAtAttribute($value)
    {
        if ($value === null) {
            return null;
        }
        // If it's already a Carbon instance (from the cast), format it as date-only
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }
        // If already a date string, return it
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10); // Take only YYYY-MM-DD part
        }
        // Otherwise try to parse and format
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Get the value of date-only fields as date strings (YYYY-MM-DD) to avoid timezone issues.
     */
    public function getLastMajorRedesignAtActualAttribute($value)
    {
        if ($value === null) {
            return null;
        }
        // If it's already a Carbon instance (from the cast), format it as date-only
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }
        // If already a date string, return it
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10); // Take only YYYY-MM-DD part
        }
        // Otherwise try to parse and format
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return $value;
        }
    }
}
