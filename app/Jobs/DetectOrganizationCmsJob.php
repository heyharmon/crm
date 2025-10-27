<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\CmsDetection\OrganizationCmsDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectOrganizationCmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $organizationId)
    {
    }

    public function handle(OrganizationCmsDetectionService $detectionService): void
    {
        $organization = Organization::find($this->organizationId);

        if (!$organization) {
            Log::warning('Skipping CMS detection for missing organization', [
                'organization_id' => $this->organizationId,
            ]);
            return;
        }

        if (!$organization->website) {
            Log::info('Skipping CMS detection for organization without website', [
                'organization_id' => $organization->id,
            ]);
            $organization->updateQuietly(['cms' => null]);
            return;
        }

        $detectionService->detectForOrganization($organization);
    }
}
