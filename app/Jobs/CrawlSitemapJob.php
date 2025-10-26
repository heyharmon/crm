<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\OrganizationSitemapSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlSitemapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $organizationId)
    {
    }

    public function handle(OrganizationSitemapSyncService $sitemapSyncService): void
    {
        $organization = Organization::find($this->organizationId);

        if (!$organization) {
            Log::warning('Skipping sitemap crawl for missing organization', [
                'organization_id' => $this->organizationId,
            ]);
            return;
        }

        if (!$organization->website) {
            Log::warning('Skipping sitemap crawl for organization without website', [
                'organization_id' => $organization->id,
            ]);
            return;
        }

        $sitemapSyncService->sync($organization);
    }
}
