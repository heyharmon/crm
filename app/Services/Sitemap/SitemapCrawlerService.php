<?php

namespace App\Services\Sitemap;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

class SitemapCrawlerService
{
    private const MAX_SITEMAP_FETCHES = 30;
    private const MAX_URLS = 5000;
    private const MAX_CONSECUTIVE_FETCH_FAILURES = 3;
    private const REQUEST_TIMEOUT_SECONDS = 8;
    private const ROBOTS_TIMEOUT_SECONDS = 5;

    public function crawl(string $websiteUrl): array
    {
        $normalizedBase = $this->normalizeWebsite($websiteUrl);
        if (!$normalizedBase) {
            return [];
        }

        $queue = $this->initialSitemapCandidates($normalizedBase);
        $visited = [];
        $collected = [];
        $consecutiveFailures = 0;

        while (!empty($queue) && count($visited) < self::MAX_SITEMAP_FETCHES && count($collected) < self::MAX_URLS) {
            $sitemapUrl = array_shift($queue);
            if (!$sitemapUrl || isset($visited[$sitemapUrl])) {
                continue;
            }

            $visited[$sitemapUrl] = true;

            $xml = $this->fetchXml($sitemapUrl);
            if (!$xml instanceof SimpleXMLElement) {
                $consecutiveFailures++;
                if ($consecutiveFailures >= self::MAX_CONSECUTIVE_FETCH_FAILURES) {
                    break;
                }

                continue;
            }

            $consecutiveFailures = 0;

            $rootName = strtolower($xml->getName());

            if ($rootName === 'sitemapindex') {
                $childSitemaps = $this->extractLocValues($xml, 'sitemap');
                foreach ($childSitemaps as $childUrl) {
                    if ($childUrl && !isset($visited[$childUrl])) {
                        $queue[] = $childUrl;
                    }
                }
                continue;
            }

            if ($rootName === 'urlset' || empty($collected)) {
                $urls = $this->extractLocValues($xml, 'url');
                foreach ($urls as $url) {
                    if ($url === '') {
                        continue;
                    }
                    $collected[$url] = true;
                    if (count($collected) >= self::MAX_URLS) {
                        break 2;
                    }
                }
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

    private function initialSitemapCandidates(string $baseUrl): array
    {
        $candidates = [
            $baseUrl . '/sitemap.xml',
            $baseUrl . '/sitemap_index.xml',
            $baseUrl . '/sitemap-index.xml',
            $baseUrl . '/sitemap/index.xml',
            $baseUrl . '/wp-sitemap.xml',
        ];

        $robots = $this->fetchRobotsSitemaps($baseUrl);
        $candidates = array_merge($candidates, $robots);

        return array_values(array_unique(array_filter($candidates)));
    }

    private function fetchRobotsSitemaps(string $baseUrl): array
    {
        $robotsUrl = $baseUrl . '/robots.txt';

        try {
            $response = Http::timeout(self::ROBOTS_TIMEOUT_SECONDS)->accept('text/plain')->get($robotsUrl);
        } catch (\Throwable $e) {
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $sitemaps = [];
        foreach (explode("\n", $response->body()) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (Str::startsWith(Str::lower($line), 'sitemap:')) {
                $sitemapUrl = trim(Str::after($line, ':'));
                if ($sitemapUrl) {
                    if (!Str::startsWith($sitemapUrl, ['http://', 'https://'])) {
                        $sitemapUrl = $baseUrl . '/' . ltrim($sitemapUrl, '/');
                    }
                    $sitemaps[] = $sitemapUrl;
                }
            }
        }

        return $sitemaps;
    }

    private function fetchXml(string $url): ?SimpleXMLElement
    {
        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->accept('application/xml')
                ->accept('text/xml')
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('Failed fetching sitemap XML', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!$response->successful()) {
            Log::warning('Sitemap request returned non-200', [
                'url' => $url,
                'status' => $response->status(),
            ]);
            return null;
        }

        $body = trim($response->body());
        if ($body === '') {
            return null;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA);
        if ($xml === false) {
            Log::warning('Unable to parse sitemap XML', [
                'url' => $url,
                'errors' => libxml_get_errors(),
            ]);
            libxml_clear_errors();
            return null;
        }
        libxml_clear_errors();

        return $xml;
    }

    private function extractLocValues(SimpleXMLElement $xml, string $parentNode): array
    {
        $paths = ["//{$parentNode}/loc"];

        $prefix = $this->registerDefaultNamespace($xml);
        if ($prefix) {
            $paths[] = "//{$prefix}:{$parentNode}/{$prefix}:loc";
            $paths[] = "//{$prefix}:{$parentNode}/loc";
            $paths[] = "//{$parentNode}/{$prefix}:loc";
        }

        foreach ($paths as $path) {
            $nodes = $xml->xpath($path);
            if (empty($nodes)) {
                continue;
            }

            $values = [];
            foreach ($nodes as $node) {
                $value = trim((string) $node);
                if ($value !== '') {
                    $values[] = $value;
                }
            }

            if (!empty($values)) {
                return $values;
            }
        }

        return [];
    }

    private function registerDefaultNamespace(SimpleXMLElement $xml): ?string
    {
        $namespaces = $xml->getNamespaces(true);
        $default = $namespaces[''] ?? null;
        if (!$default && !empty($namespaces)) {
            $default = reset($namespaces);
        }

        if ($default) {
            $prefix = 'sm';
            $xml->registerXPathNamespace($prefix, $default);
            return $prefix;
        }

        return null;
    }
}
