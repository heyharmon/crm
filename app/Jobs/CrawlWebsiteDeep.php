<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\Sitemap\DeepWebsiteCrawlerService;
use App\Services\Sitemap\OrganizationSitemapSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlWebsiteDeep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $organizationId) {}

    public function handle(
        DeepWebsiteCrawlerService $deepCrawler,
        OrganizationSitemapSyncService $sitemapSyncService
    ): void {
        $organization = Organization::find($this->organizationId);

        if (!$organization) {
            Log::warning('Skipping deep crawl for missing organization', [
                'organization_id' => $this->organizationId,
            ]);
            return;
        }

        if (!$organization->website) {
            Log::warning('Skipping deep crawl for organization without website', [
                'organization_id' => $organization->id,
            ]);
            return;
        }

        try {
            $urls = $deepCrawler->crawl($organization->website);

            if (empty($urls)) {
                $organization->update([
                    'website_crawl_status' => 'failed',
                    'website_crawl_message' => 'Deep crawl found no pages',
                ]);
                return;
            }

            $result = $sitemapSyncService->syncUrls($urls, $organization);

            $organization->update([
                'website_crawl_status' => $result['status'],
                'website_crawl_message' => $result['message'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Unexpected error during deep crawl', [
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $organization->update([
                'website_crawl_status' => 'failed',
                'website_crawl_message' => 'Deep crawl failed: ' . $e->getMessage(),
            ]);
        }
    }
}
