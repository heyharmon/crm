<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationWebsiteRedesign extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'before_wayback_timestamp',
        'before_captured_at',
        'after_wayback_timestamp',
        'after_captured_at',
        'nav_similarity',
        'before_nav_class_count',
        'after_nav_class_count',
        'before_nav_classes',
        'after_nav_classes',
        'before_nav_html',
        'after_nav_html',
    ];

    protected $casts = [
        'before_captured_at' => 'datetime',
        'after_captured_at' => 'datetime',
        'nav_similarity' => 'float',
        'before_nav_classes' => 'array',
        'after_nav_classes' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
