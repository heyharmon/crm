<?php

namespace App\Http\Controllers;

use App\Jobs\DetectWebsiteRedesignJob;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class OrganizationWebsiteRedesignController extends Controller
{
    public function store(Organization $organization): JsonResponse
    {
        if (!$organization->website) {
            return response()->json([
                'message' => 'Organization does not have a website to analyze.',
            ], 422);
        }

        DetectWebsiteRedesignJob::dispatch($organization->id);

        return response()->json([
            'message' => 'Website redesign detection has been queued.',
            'organization_id' => $organization->id,
        ]);
    }
}
