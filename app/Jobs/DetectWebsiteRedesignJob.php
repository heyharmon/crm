<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\WebsiteRedesign\WebsiteRedesignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that refreshes Wayback redesign data for a single organization.
 * Runs the detector, replaces persisted snapshot pairs, and updates the cached status.
 */
class DetectWebsiteRedesignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(private int $organizationId) {}

    public function handle(WebsiteRedesignService $redesignService): void
    {
        $organization = Organization::find($this->organizationId);

        if (!$organization) {
            Log::warning('Website redesign job skipped missing organization', [
                'organization_id' => $this->organizationId,
            ]);
            return;
        }

        if (!$organization->website) {
            return;
        }

        // The service handles clearing old records and writing newly detected snapshot pairs.
        $redesignService->refreshOrganizationRedesigns($organization);
    }
}
