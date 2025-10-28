<?php

namespace App\Http\Controllers;

use App\Jobs\CheckOrganizationWebsiteStatus;
use App\Models\Organization;

class OrganizationWebsiteStatusController extends Controller
{
    public function store(Organization $organization)
    {
        CheckOrganizationWebsiteStatus::dispatch($organization->id);

        return response()->json([
            'message' => 'Website status check queued.',
            'queued' => true,
            'organization_id' => $organization->id,
        ], 202);
    }
}
