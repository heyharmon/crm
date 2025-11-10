<?php

namespace App\Services\Sitemap;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeepWebsiteCrawlerService
{
    private const MAX_URLS = 150;
    private const REQUEST_TIMEOUT_SECONDS = 10;

    public function crawl(string $websiteUrl): array
    {
        $normalizedBase = $this->normalizeWebsite($websiteUrl);
        if (!$normalizedBase) {
            return [];
        }

        $baseDomain = $this->extractDomain($normalizedBase);
        if (!$baseDomain) {
            return [];
        }

        $visited = [];
        $queue = [$normalizedBase];
        $collected = [];

        while (!empty($queue) && count($collected) < self::MAX_URLS) {
            $currentUrl = array_shift($queue);

            if (isset($visited[$currentUrl])) {
                continue;
            }

            $visited[$currentUrl] = true;

            try {
                $html = $this->fetchHtml($currentUrl);
                if (!$html) {
                    continue;
                }

                // Add current URL to collected
                $collected[$currentUrl] = true;

                // Extract internal links
                $internalLinks = $this->extractInternalLinks($html, $currentUrl, $baseDomain);

                // Add new internal links to queue
                foreach ($internalLinks as $link) {
                    $normalizedLink = $this->normalizeUrl($link, $currentUrl);
                    if ($normalizedLink && !isset($visited[$normalizedLink]) && count($collected) < self::MAX_URLS) {
                        $queue[] = $normalizedLink;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to crawl URL during deep crawl', [
                    'url' => $currentUrl,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return array_keys($collected);
    }

    private function normalizeWebsite(?string $website): ?string
    {
        if (!$website) {
            return null;
        }

        $website = trim($website);
        if ($website === '') {
            return null;
        }

        if (!Str::startsWith($website, ['http://', 'https://'])) {
            $website = 'https://' . ltrim($website, '/');
        }

        return rtrim($website, '/');
    }

    private function extractDomain(string $url): ?string
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return null;
        }

        $host = $parsed['host'];
        // Remove www. prefix for domain matching
        $host = preg_replace('/^www\./', '', $host);

        return strtolower($host);
    }

    private function normalizeUrl(string $url, string $baseUrl): ?string
    {
        // Remove fragment (anchor) since it points to the same page
        if (($pos = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $pos);
        }

        // Handle relative URLs
        if (Str::startsWith($url, '//')) {
            $url = 'https:' . $url;
        } elseif (Str::startsWith($url, '/')) {
            $parsed = parse_url($baseUrl);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? '';
            $url = $scheme . '://' . $host . $url;
        } elseif (!Str::startsWith($url, ['http://', 'https://'])) {
            // Relative URL without leading slash
            $parsed = parse_url($baseUrl);
            $scheme = $parsed['scheme'] ?? 'https';
            $host = $parsed['host'] ?? '';
            $path = $parsed['path'] ?? '/';
            $path = rtrim($path, '/') . '/';
            $url = $scheme . '://' . $host . $path . $url;
        }

        // Normalize the URL (removes trailing slash but preserves query strings)
        $normalized = $this->normalizeWebsite($url);
        if (!$normalized) {
            return null;
        }

        return $normalized;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->accept('text/html')
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $body = $response->body();
            return $body ?: null;
        } catch (\Throwable $e) {
            Log::warning('Failed fetching HTML during deep crawl', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function extractInternalLinks(string $html, string $currentUrl, string $baseDomain): array
    {
        $links = [];

        // Use regex to find all href attributes
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);

        if (empty($matches[1])) {
            return [];
        }

        foreach ($matches[1] as $href) {
            $href = trim($href);

            // Skip empty, anchors, javascript, mailto, tel, etc.
            if (
                $href === '' ||
                Str::startsWith($href, '#') ||
                Str::startsWith($href, 'javascript:') ||
                Str::startsWith($href, 'mailto:') ||
                Str::startsWith($href, 'tel:') ||
                Str::startsWith($href, 'data:') ||
                Str::startsWith($href, 'file:') ||
                Str::startsWith($href, 'ftp:')
            ) {
                continue;
            }

            $normalizedLink = $this->normalizeUrl($href, $currentUrl);
            if (!$normalizedLink) {
                continue;
            }

            // Check if link belongs to the same domain
            $linkDomain = $this->extractDomain($normalizedLink);
            if ($linkDomain && $linkDomain === $baseDomain) {
                $links[] = $normalizedLink;
            }
        }

        return array_unique($links);
    }
}
