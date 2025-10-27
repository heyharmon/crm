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

class DetectWebsiteRedesignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

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

        $redesignService->refreshOrganizationRedesigns($organization);
    }
}
