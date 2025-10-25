<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationWebsiteRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'website_rating_option_id',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(WebsiteRatingOption::class, 'website_rating_option_id');
    }
}

