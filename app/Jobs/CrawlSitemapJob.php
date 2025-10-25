<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\Page;
use App\Services\SitemapCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrawlSitemapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $organizationId)
    {
    }

    public function handle(SitemapCrawlerService $crawler): void
    {
        $organization = Organization::find($this->organizationId);

        if (!$organization || !$organization->website) {
            Log::warning('Skipping sitemap crawl for organization without website', [
                'organization_id' => $this->organizationId,
            ]);
            return;
        }

        $urls = $crawler->crawl($organization->website);

        if (empty($urls)) {
            Log::info('No URLs discovered from sitemap crawl', [
                'organization_id' => $organization->id,
            ]);
            return;
        }

        $processed = 0;
        foreach ($urls as $url) {
            $title = $this->deriveTitle($url, $organization->name);

            Page::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'url' => $url,
                ],
                [
                    'title' => $title,
                ]
            );

            $processed++;
        }

        Log::info('Sitemap crawl completed', [
            'organization_id' => $organization->id,
            'pages_processed' => $processed,
        ]);
    }

    private function deriveTitle(string $url, string $organizationName): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $path = trim($path, '/');

        if ($path === '') {
            return $organizationName . ' Homepage';
        }

        $segments = explode('/', $path);
        $last = end($segments) ?: $path;
        $last = preg_replace('/\.[a-z0-9]+$/i', '', $last) ?? $last;

        $title = Str::headline(str_replace(['-', '_'], ' ', $last));

        return $title ?: $organizationName;
    }
}
