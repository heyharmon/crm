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
        'digest',
        'wayback_timestamp',
        'captured_at',
        'persistence_days',
        'is_major',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'is_major' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
