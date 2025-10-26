<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrganizationSitemapSyncService
{
    public function __construct(private SitemapCrawlerService $crawler)
    {
    }

    public function sync(Organization $organization): int
    {
        $urls = $this->crawler->crawl($organization->website);

        if (empty($urls)) {
            Log::info('No URLs discovered from sitemap crawl', [
                'organization_id' => $organization->id,
            ]);

            return 0;
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

        return $processed;
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
