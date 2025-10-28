<?php

namespace App\Services\CmsDetection;

use App\Models\Organization;
use App\Support\WebsiteUrl;
use Illuminate\Support\Facades\Log;

class OrganizationCmsDetectionService
{
    public function __construct(private WhatCmsClient $client)
    {
    }

    public function detectForOrganization(Organization $organization): void
    {
        $normalizedWebsite = WebsiteUrl::normalize($organization->website);

        if (!$normalizedWebsite) {
            $organization->updateQuietly(['cms' => null]);
            return;
        }

        $cms = $this->client->detectCms($normalizedWebsite);

        $organization->updateQuietly(['cms' => $cms]);

        Log::info('CMS detection completed for organization', [
            'organization_id' => $organization->id,
            'website' => $normalizedWebsite,
            'cms' => $cms,
        ]);
    }
}
