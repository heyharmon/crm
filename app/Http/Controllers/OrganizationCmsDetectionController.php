<?php

namespace App\Http\Controllers;

use App\Jobs\DetectOrganizationCmsJob;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class OrganizationCmsDetectionController extends Controller
{
    public function store(Organization $organization): JsonResponse
    {
        if (!$organization->website) {
            return response()->json([
                'message' => 'This organization does not have a website to analyze.',
            ], 422);
        }

        DetectOrganizationCmsJob::dispatch($organization->id);

        return response()->json([
            'message' => 'CMS detection queued.',
        ], 202);
    }
}
