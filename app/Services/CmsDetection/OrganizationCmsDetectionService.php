<?php

namespace App\Services\CmsDetection;

use App\Models\Organization;
use App\Services\CmsDetection\Exceptions\RateLimitedException;
use App\Support\WebsiteUrl;
use Illuminate\Support\Facades\Log;

class OrganizationCmsDetectionService
{
    private const RATE_LIMIT_DELAY_SECONDS = 10;
    private const NOT_DETECTABLE_VALUE = 'Unknown';

    public function __construct(private WhatCmsClient $client) {}

    public function detectForOrganization(Organization $organization): void
    {
        $normalizedWebsite = WebsiteUrl::normalize($organization->website);

        if (!$normalizedWebsite) {
            $organization->updateQuietly(['cms' => self::NOT_DETECTABLE_VALUE]);
            return;
        }

        $this->waitForRateLimit();

        $cms = $this->detectCmsWithRetry($normalizedWebsite);

        $storedCms = $cms ?? self::NOT_DETECTABLE_VALUE;

        $organization->updateQuietly(['cms' => $storedCms]);

        Log::info('CMS detection completed for organization', [
            'organization_id' => $organization->id,
            'website' => $normalizedWebsite,
            'cms' => $storedCms,
        ]);
    }

    private function detectCmsWithRetry(string $website): ?string
    {
        try {
            return $this->client->detectCms($website);
        } catch (RateLimitedException $exception) {
            Log::info('Retrying CMS detection after rate limit delay', [
                'website' => $website,
            ]);

            $this->waitForRateLimit();

            try {
                return $this->client->detectCms($website);
            } catch (RateLimitedException $retryException) {
                Log::warning('CMS detection still rate limited after retry', [
                    'website' => $website,
                ]);
                return null;
            }
        }
    }

    private function waitForRateLimit(): void
    {
        sleep(self::RATE_LIMIT_DELAY_SECONDS);
    }
}
