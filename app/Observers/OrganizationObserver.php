<?php

namespace App\Observers;

use App\Jobs\CheckOrganizationWebsiteStatus;
use App\Models\Organization;

class OrganizationObserver
{
    public function created(Organization $organization): void
    {
        CheckOrganizationWebsiteStatus::dispatch($organization->id);
    }
}
