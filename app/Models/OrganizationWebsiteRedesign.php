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
        'before_html_class_count',
        'after_html_class_count',
        'before_body_class_count',
        'after_body_class_count',
        'before_head_asset_count',
        'after_head_asset_count',
        'before_html_classes',
        'after_html_classes',
        'before_body_classes',
        'after_body_classes',
        'before_head_assets',
        'after_head_assets',
        'before_head_html',
        'after_head_html',
    ];

    protected $casts = [
        'before_captured_at' => 'datetime',
        'after_captured_at' => 'datetime',
        'nav_similarity' => 'float',
        'before_html_classes' => 'array',
        'after_html_classes' => 'array',
        'before_body_classes' => 'array',
        'after_body_classes' => 'array',
        'before_head_assets' => 'array',
        'after_head_assets' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
