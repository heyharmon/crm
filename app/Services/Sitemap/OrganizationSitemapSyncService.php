<?php

namespace App\Services\Sitemap;

use App\Jobs\CrawlWebsiteDeep;
use App\Models\Organization;
use App\Models\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrganizationSitemapSyncService
{
    public function __construct(private SitemapCrawlerService $crawler) {}

    public function sync(Organization $organization): array
    {
        if (!$organization->website) {
            return [
                'status' => 'failed',
                'message' => 'Organization has no website URL',
                'pages_found' => 0,
            ];
        }

        try {
            $urls = $this->crawler->crawl($organization->website);

            if (empty($urls)) {
                // Automatically trigger deep crawl when no sitemap is found
                CrawlWebsiteDeep::dispatch($organization->id);

                return [
                    'status' => 'pending',
                    'message' => 'No sitemap found. Deep crawl initiated.',
                    'pages_found' => 0,
                ];
            }

            return $this->syncUrls($urls, $organization);
        } catch (\Throwable $e) {
            Log::error('Failed to crawl sitemap for organization', [
                'organization_id' => $organization->id,
                'website' => $organization->website,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'failed',
                'message' => 'Crawl failed: ' . $e->getMessage(),
                'pages_found' => 0,
            ];
        }
    }

    public function syncUrls(array $urls, Organization $organization): array
    {
        if (empty($urls)) {
            return [
                'status' => 'failed',
                'message' => 'No URLs to process',
                'pages_found' => 0,
            ];
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

        return [
            'status' => 'success',
            'message' => "Successfully crawled {$processed} page(s)",
            'pages_found' => $processed,
        ];
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
