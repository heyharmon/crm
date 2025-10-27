<?php

namespace App\Services\WebsiteRedesign;

use App\Support\WebsiteRedesignDetectionResult;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Detects website redesigns by comparing site-shell fingerprints across Wayback snapshots.
 *
 * Workflow:
 *  - collect coarse yearly snapshots and build shell signatures (html/body classes + head assets)
 *  - flag large signature changes to highlight candidate redesign windows
 *  - drill into monthly snapshots to find the first capture that reflects the new shell
 */
class WebsiteRedesignDetector
{
    private const WAYBACK_USER_AGENT = 'HerdCRM/WaybackDetector';

    /**
     * @var array<string, array|null>
     */
    private array $signatureCache = [];

    /**
     * Entry point used by the service. Returns a before/after snapshot pair for each detected redesign.
     */
    public function detect(?string $website): WebsiteRedesignDetectionResult
    {
        $normalized = $this->normalizeWebsite($website);
        if (!$normalized) {
            return WebsiteRedesignDetectionResult::noWaybackData('Website URL is missing or invalid.');
        }

        $yearlySnapshots = $this->fetchYearlySnapshots($normalized);

        if ($failure = $this->failureFromSnapshotFetch(
            $yearlySnapshots,
            'Wayback Machine did not return any snapshots for this website.',
            'Wayback Machine request failed.'
        )) {
            return $failure;
        }

        // Build coarse timeline of shell signatures using yearly snapshots.
        $yearlyTimeline = $this->buildSignatureTimeline($normalized, $yearlySnapshots['snapshots']);

        // Identify the windows where the shell changed enough to investigate further.
        $changeWindows = $this->detectChangeWindows($yearlyTimeline);
        if (empty($changeWindows)) {
            return WebsiteRedesignDetectionResult::noMajorEvents(
                'Site shell analysis did not detect any major redesigns.'
            );
        }

        $events = [];
        foreach ($changeWindows as $window) {
            // Drill into monthly snapshots to pinpoint the first capture of the new shell.
            $event = $this->refineChangeWindow($normalized, $window);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        if (empty($events)) {
            return WebsiteRedesignDetectionResult::noMajorEvents(
                'Unable to pinpoint a redesign window after refining shell changes.'
            );
        }

        $maxEvents = max(1, (int) config('waybackmachine.max_events', 5));
        if (count($events) > $maxEvents) {
            $events = array_slice($events, -$maxEvents);
        }

        return WebsiteRedesignDetectionResult::success($events);
    }

    /**
     * Builds a chronological list of snapshots enriched with their extracted signature.
     *
     * @param array<int, array{timestamp: string, captured_at: Carbon, length: ?int}> $snapshots
     * @return array<int, array{timestamp: string, captured_at: Carbon, signature: ?array}>
     */
    private function buildSignatureTimeline(string $host, array $snapshots): array
    {
        $timeline = [];

        foreach ($snapshots as $snapshot) {
            $timeline[] = [
                'timestamp' => $snapshot['timestamp'],
                'captured_at' => $snapshot['captured_at'],
                'signature' => $this->getSnapshotSignatureForTimestamp($host, $snapshot['timestamp']),
            ];
        }

        usort($timeline, static fn($a, $b) => $a['captured_at'] <=> $b['captured_at']);

        return $timeline;
    }

    /**
     * Compares neighbouring signatures and records the ones that differ enough to inspect further.
     *
     * @param array<int, array{timestamp: string, captured_at: Carbon, signature: ?array}> $timeline
     * @return array<int, array{
     *     previous: array{timestamp: string, captured_at: Carbon, signature: ?array},
     *     current: array{timestamp: string, captured_at: Carbon, signature: ?array},
     *     similarity: float
     * }>
     */
    private function detectChangeWindows(array $timeline): array
    {
        $threshold = $this->signatureChangeThreshold();
        $windows = [];

        for ($index = 1, $count = count($timeline); $index < $count; $index++) {
            $previous = $timeline[$index - 1];
            $current = $timeline[$index];

            if (!$previous['signature'] || !$current['signature']) {
                continue;
            }

            $similarity = $this->calculateSimilarity($previous['signature'], $current['signature']);

            if ($similarity <= $threshold) {
                $windows[] = [
                    'previous' => $previous,
                    'current' => $current,
                    'similarity' => $similarity,
                ];
            }
        }

        return $windows;
    }

    /**
     * Zooms into the coarse window and finds the first monthly snapshot that matches the new shell.
     *
     * @param array{
     *     previous: array{timestamp: string, captured_at: Carbon, signature: ?array},
     *     current: array{timestamp: string, captured_at: Carbon, signature: ?array},
     *     similarity: float
     * } $window
     */
    private function refineChangeWindow(string $host, array $window): ?array
    {
        $previous = $window['previous'];
        $current = $window['current'];

        $previousSignature = $previous['signature'];
        $currentSignature = $current['signature'];

        if (!$previousSignature || !$currentSignature) {
            return null;
        }

        $monthlySnapshots = $this->fetchMonthlySnapshots($host, $previous['timestamp'], $current['timestamp']);

        if ($monthlySnapshots['status'] !== WebsiteRedesignDetectionResult::STATUS_SUCCESS) {
            return $this->buildEventFromSnapshot($previous, $current, $previousSignature, $currentSignature);
        }

        $monthlyTimeline = $this->buildSignatureTimeline($host, $monthlySnapshots['snapshots']);
        $monthlyTimeline = $this->mergeTimelines($monthlyTimeline, [$previous, $current]);

        $matchThreshold = $this->signatureMatchThreshold();
        $eventEntry = null;

        foreach ($monthlyTimeline as $entry) {
            if ($entry['timestamp'] <= $previous['timestamp']) {
                continue;
            }

            if (!$entry['signature']) {
                continue;
            }

            $similarityToNew = $this->calculateSimilarity($entry['signature'], $currentSignature);
            $similarityToOld = $this->calculateSimilarity($entry['signature'], $previousSignature);

            if ($similarityToNew >= $matchThreshold && $similarityToNew >= $similarityToOld) {
                $eventEntry = $entry;
                break;
            }
        }

        if ($eventEntry === null) {
            $eventEntry = $current;
        }

        return $this->buildEventFromSnapshot($previous, $eventEntry, $previousSignature, $currentSignature);
    }

    /**
     * Ensures that the coarse snapshots and required comparison points are merged into a single ordered list.
     *
     * @param array<int, array{timestamp: string, captured_at: Carbon, signature: ?array}> $timeline
     * @param array<int, array{timestamp: string, captured_at: Carbon, signature: ?array}> $required
     * @return array<int, array{timestamp: string, captured_at: Carbon, signature: ?array}>
     */
    private function mergeTimelines(array $timeline, array $required): array
    {
        $indexed = [];

        foreach ($timeline as $entry) {
            $indexed[$entry['timestamp']] = $entry;
        }

        foreach ($required as $entry) {
            $indexed[$entry['timestamp']] = $entry;
        }

        $merged = array_values($indexed);
        usort($merged, static fn($a, $b) => $a['captured_at'] <=> $b['captured_at']);

        return $merged;
    }

    /**
     * Converts the two boundary snapshots into the payload stored by the application.
     *
     * @param array{timestamp: string, captured_at: Carbon, signature: ?array} $previous
     * @param array{timestamp: string, captured_at: Carbon, signature: ?array} $event
     * @param array $previousSignature
     * @param array $eventSignature
     * @return array{
     *     before_timestamp: string,
     *     before_captured_at: Carbon,
     *     after_timestamp: string,
     *     after_captured_at: Carbon,
     *     nav_similarity: ?float,
     *     before_html_class_count: ?int,
     *     after_html_class_count: ?int,
     *     before_body_class_count: ?int,
     *     after_body_class_count: ?int,
     *     before_head_asset_count: ?int,
     *     after_head_asset_count: ?int,
     *     before_html_classes: array<int, string>,
     *     after_html_classes: array<int, string>,
     *     before_body_classes: array<int, string>,
     *     after_body_classes: array<int, string>,
     *     before_head_assets: array<int, string>,
     *     after_head_assets: array<int, string>,
     *     before_head_html: ?string,
     *     after_head_html: ?string
     * }
     */
    private function buildEventFromSnapshot(array $previous, array $event, array $previousSignature, array $eventSignature): array
    {
        $afterSignature = $event['signature'] ?? $eventSignature;

        $navSimilarity = null;
        if (!empty($afterSignature)) {
            $navSimilarity = $this->calculateSimilarity($previousSignature, $afterSignature);
        }

        return [
            'before_timestamp' => $previous['timestamp'],
            'before_captured_at' => $previous['captured_at'],
            'after_timestamp' => $event['timestamp'],
            'after_captured_at' => $event['captured_at'],
            'nav_similarity' => $navSimilarity,
            'before_html_class_count' => $previousSignature['html_class_count'] ?? null,
            'after_html_class_count' => $afterSignature['html_class_count'] ?? null,
            'before_body_class_count' => $previousSignature['body_class_count'] ?? null,
            'after_body_class_count' => $afterSignature['body_class_count'] ?? null,
            'before_head_asset_count' => $previousSignature['head_asset_count'] ?? null,
            'after_head_asset_count' => $afterSignature['head_asset_count'] ?? null,
            'before_html_classes' => $previousSignature['html_classes'] ?? [],
            'after_html_classes' => $afterSignature['html_classes'] ?? [],
            'before_body_classes' => $previousSignature['body_classes'] ?? [],
            'after_body_classes' => $afterSignature['body_classes'] ?? [],
            'before_head_assets' => $previousSignature['head_assets'] ?? [],
            'after_head_assets' => $afterSignature['head_assets'] ?? [],
            'before_head_html' => $previousSignature['head_html'] ?? null,
            'after_head_html' => $afterSignature['head_html'] ?? null,
        ];
    }

    /**
     * Fetches a collapsed set of yearly snapshots to get a broad view of shell changes.
     *
     * @return array{
     *     snapshots: array<int, array{timestamp: string, captured_at: Carbon, length: ?int}>,
     *     status: string,
     *     message: ?string
     * }
     */
    private function fetchYearlySnapshots(string $host): array
    {
        return $this->fetchSnapshots($host, [
            'collapse' => 'timestamp:4',
            'limit' => min(500, (int) config('waybackmachine.max_snapshot_results', 2000)),
        ]);
    }

    /**
     * Fetches monthly snapshots between the two coarse boundaries to narrow down the redesign month.
     *
     * @return array{
     *     snapshots: array<int, array{timestamp: string, captured_at: Carbon, length: ?int}>,
     *     status: string,
     *     message: ?string
     * }
     */
    private function fetchMonthlySnapshots(string $host, string $from, string $to): array
    {
        return $this->fetchSnapshots($host, [
            'from' => $from,
            'to' => $to,
            'collapse' => 'timestamp:6',
            'limit' => (int) config('waybackmachine.max_snapshot_results', 2000),
        ]);
    }

    /**
     * Low-level helper that talks to the CDX API using the provided filters.
     *
     * @param array{
     *     collapse?: string,
     *     from?: string,
     *     to?: string,
     *     limit?: int
     * } $options
     * @return array{
     *     snapshots: array<int, array{timestamp: string, captured_at: Carbon, length: ?int}>,
     *     status: string,
     *     message: ?string
     * }
     */
    private function fetchSnapshots(string $host, array $options = []): array
    {
        $timeoutSeconds = max(5, (int) config('waybackmachine.request_timeout_seconds', 120));
        $minLength = max(0, (int) config('waybackmachine.min_snapshot_length_bytes', 8192));

        $params = [
            'url' => $host,
            'output' => 'json',
            'fl' => 'timestamp,statuscode,mimetype,length',
            'filter' => [
                'statuscode:200',
                'mimetype:text/html',
            ],
        ];

        if (!empty($options['collapse'])) {
            $params['collapse'] = $options['collapse'];
        }

        if (!empty($options['from'])) {
            $params['from'] = $options['from'];
        }

        if (!empty($options['to'])) {
            $params['to'] = $options['to'];
        }

        if (!empty($options['limit'])) {
            $params['limit'] = $options['limit'];
        }

        try {
            $response = Http::timeout($timeoutSeconds)
                ->acceptJson()
                ->get(config('waybackmachine.cdx_endpoint'), $params);
        } catch (\Throwable $exception) {
            Log::warning('Wayback CDX request failed', [
                'website' => $host,
                'error' => $exception->getMessage(),
            ]);

            return [
                'snapshots' => [],
                'status' => WebsiteRedesignDetectionResult::STATUS_WAYBACK_FAILED,
                'message' => $exception->getMessage(),
            ];
        }

        if (!$response->successful()) {
            Log::warning('Wayback CDX response returned non-200', [
                'website' => $host,
                'status' => $response->status(),
            ]);

            return [
                'snapshots' => [],
                'status' => WebsiteRedesignDetectionResult::STATUS_WAYBACK_FAILED,
                'message' => sprintf('Wayback Machine responded with HTTP %s.', $response->status()),
            ];
        }

        $payload = $response->json();

        if (!is_array($payload) || count($payload) <= 1) {
            return [
                'snapshots' => [],
                'status' => WebsiteRedesignDetectionResult::STATUS_NO_WAYBACK_DATA,
                'message' => 'Wayback Machine did not return any snapshots.',
            ];
        }

        $rows = array_slice($payload, 1);
        $snapshots = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row[0])) {
                continue;
            }

            $timestamp = (string) $row[0];
            $length = isset($row[3]) && is_numeric($row[3]) ? (int) $row[3] : null;

            if ($length !== null && $length < $minLength) {
                continue;
            }

            try {
                $capturedAt = Carbon::createFromFormat('YmdHis', $timestamp, 'UTC');
            } catch (\Throwable $exception) {
                Log::debug('Skipping invalid Wayback timestamp', [
                    'timestamp' => $timestamp,
                    'error' => $exception->getMessage(),
                ]);
                continue;
            }

            $snapshots[] = [
                'timestamp' => $timestamp,
                'captured_at' => $capturedAt,
                'length' => $length,
            ];
        }

        if (empty($snapshots)) {
            return [
                'snapshots' => [],
                'status' => WebsiteRedesignDetectionResult::STATUS_NO_WAYBACK_DATA,
                'message' => 'Wayback snapshots were filtered out by the current filters.',
            ];
        }

        usort($snapshots, static fn($a, $b) => $a['captured_at'] <=> $b['captured_at']);

        return [
            'snapshots' => $snapshots,
            'status' => WebsiteRedesignDetectionResult::STATUS_SUCCESS,
            'message' => null,
        ];
    }

    /**
     * Strips schemes and validates the hostname we send to Wayback.
     */
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

        $parts = parse_url($website);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        return $parts['host'];
    }

    /**
     * Minimum similarity score required for two shell signatures to be considered different enough.
     */
    private function signatureChangeThreshold(): float
    {
        $value = (float) config('waybackmachine.nav_similarity_change_threshold', 0.6);

        return max(0.0, min(1.0, $value));
    }

    /**
     * Similarity threshold used when confirming the first snapshot of the new design.
     */
    private function signatureMatchThreshold(): float
    {
        $value = (float) config('waybackmachine.nav_similarity_match_threshold', 0.75);

        return max(0.0, min(1.0, $value));
    }

    /**
     * Retrieves (or caches) the shell signature for the given snapshot timestamp.
     *
     * @return array{
     *     hash: string,
     *     html_classes: array<int, string>,
     *     html_class_count: int,
     *     body_classes: array<int, string>,
     *     body_class_count: int,
     *     head_assets: array<int, string>,
     *     head_asset_count: int,
     *     head_html: ?string
     * }|null
     */
    private function getSnapshotSignatureForTimestamp(string $host, string $timestamp): ?array
    {
        if (array_key_exists($timestamp, $this->signatureCache)) {
            return $this->signatureCache[$timestamp];
        }

        $html = $this->fetchSnapshotHtml($host, $timestamp);

        if ($html === null) {
            $this->signatureCache[$timestamp] = null;

            return null;
        }

        $signature = $this->extractSnapshotSignature($html);

        $this->signatureCache[$timestamp] = $signature;

        return $signature;
    }

    /**
     * Downloads the archived HTML for a specific snapshot, handling timeouts and content filtering.
     */
    private function fetchSnapshotHtml(string $host, string $timestamp): ?string
    {
        $timeoutSeconds = max(5, (int) config('waybackmachine.request_timeout_seconds', 120));
        $delayMs = max(0, (int) config('waybackmachine.request_delay_ms', 1000));

        $baseUrls = [
            'https://' . $host . '/',
            'http://' . $host . '/',
        ];

        foreach ($baseUrls as $baseUrl) {
            $archiveUrl = sprintf('https://web.archive.org/web/%sid_/%s', $timestamp, $baseUrl);

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            try {
                $response = Http::timeout($timeoutSeconds)
                    ->withHeaders([
                        'User-Agent' => 'HerdCRM/WaybackDetector',
                    ])
                    ->get($archiveUrl);
            } catch (\Throwable $exception) {
                Log::debug('Wayback HTML fetch failed', [
                    'website' => $host,
                    'timestamp' => $timestamp,
                    'error' => $exception->getMessage(),
                ]);
                continue;
            }

            if (!$response->successful()) {
                continue;
            }

            $contentType = $response->header('Content-Type');
            if ($contentType && !Str::contains(Str::lower($contentType), 'text/html')) {
                continue;
            }

            $body = $response->body();

            if (trim($body) === '') {
                continue;
            }

            return $body;
        }

        return null;
    }

    /**
     * Converts the raw snapshot HTML into a lightweight shell signature we can compare.
     *
     * @return array{
     *     hash: string,
     *     html_classes: array<int, string>,
     *     html_class_count: int,
     *     body_classes: array<int, string>,
     *     body_class_count: int,
     *     head_assets: array<int, string>,
     *     head_asset_count: int,
     *     head_html: ?string
     * }|null
     */
    private function extractSnapshotSignature(string $html): ?array
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);

        if (!$dom->loadHTML($html)) {
            libxml_clear_errors();

            return null;
        }

        libxml_clear_errors();

        $htmlElement = $dom->getElementsByTagName('html')->item(0);
        $bodyElement = $dom->getElementsByTagName('body')->item(0);
        $headElement = $dom->getElementsByTagName('head')->item(0);

        if (!$htmlElement && !$bodyElement && !$headElement) {
            return null;
        }

        $htmlClasses = $this->extractClassList($htmlElement);
        $bodyClasses = $this->extractClassList($bodyElement);
        $headData = $this->collectHeadAssetTokens($dom, $headElement);

        $signatureBasisParts = [
            implode('|', $htmlClasses),
            implode('|', $bodyClasses),
            implode('|', $headData['tokens']),
        ];

        $signatureBasis = implode('||', array_filter($signatureBasisParts, static fn($value) => $value !== ''));

        if ($signatureBasis === '') {
            $signatureBasis = substr(sha1($dom->saveHTML() ?: ''), 0, 32);
        }

        return [
            'hash' => sha1($signatureBasis),
            'html_classes' => $htmlClasses,
            'html_class_count' => count($htmlClasses),
            'body_classes' => $bodyClasses,
            'body_class_count' => count($bodyClasses),
            'head_assets' => $headData['tokens'],
            'head_asset_count' => $headData['count'],
            'head_html' => $headData['html'],
        ];
    }

    /**
     * Extracts and normalizes the class list from a single element.
     */
    private function extractClassList(?DOMElement $element): array
    {
        if (!$element) {
            return [];
        }

        $classAttribute = trim((string) $element->getAttribute('class'));

        if ($classAttribute === '') {
            return [];
        }

        $classes = [];

        foreach (preg_split('/\s+/', $classAttribute) as $rawClass) {
            $normalized = Str::lower(trim($rawClass));
            if ($normalized === '') {
                continue;
            }

            $classes[] = $normalized;
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    /**
     * Collects representative tokens for the contents of the <head> element.
     */
    private function collectHeadAssetTokens(DOMDocument $dom, ?DOMElement $head): array
    {
        if (!$head) {
            return [
                'tokens' => [],
                'count' => 0,
                'html' => null,
            ];
        }

        $tokens = [];

        foreach ($head->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = Str::lower($child->tagName);
            $token = null;

            switch ($tag) {
                case 'link':
                    $rel = Str::lower(trim($child->getAttribute('rel')));
                    $href = $this->normalizeAssetReference($child->getAttribute('href'));
                    if ($href !== '') {
                        $descriptor = $rel !== '' ? $rel : 'link';
                        $token = sprintf('link:%s:%s', $descriptor, $href);
                    }
                    break;
                case 'script':
                    $src = $this->normalizeAssetReference($child->getAttribute('src'));
                    if ($src !== '') {
                        $token = sprintf('script:src:%s', $src);
                        break;
                    }

                    $content = trim($child->textContent ?? '');
                    if ($content !== '') {
                        $token = sprintf('script:inline:%s', substr(sha1($content), 0, 16));
                    }
                    break;
                case 'style':
                    $styleContent = trim($child->textContent ?? '');
                    if ($styleContent !== '') {
                        $token = sprintf('style:inline:%s', substr(sha1($styleContent), 0, 16));
                    }
                    break;
                case 'meta':
                    $name = Str::lower(trim($child->getAttribute('name') ?: $child->getAttribute('property')));
                    $content = trim($child->getAttribute('content'));
                    if ($name !== '') {
                        $token = sprintf('meta:%s:%s', $name, substr(sha1(Str::lower($content)), 0, 16));
                    }
                    break;
                case 'title':
                    $title = trim($child->textContent ?? '');
                    if ($title !== '') {
                        $token = sprintf('title:%s', substr(sha1(Str::lower($title)), 0, 16));
                    }
                    break;
                default:
                    $snippet = trim($dom->saveHTML($child) ?: '');
                    if ($snippet !== '') {
                        $token = sprintf('%s:%s', $tag, substr(sha1($snippet), 0, 16));
                    }
                    break;
            }

            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        $headHtml = trim($dom->saveHTML($head) ?: '');

        if (empty($tokens) && $headHtml !== '') {
            $tokens[] = 'head-html:' . substr(sha1($headHtml), 0, 16);
        }

        return [
            'tokens' => $tokens,
            'count' => count($tokens),
            'html' => $headHtml !== '' ? $headHtml : null,
        ];
    }

    /**
     * Normalizes asset references so CDN/version noise compares consistently.
     */
    private function normalizeAssetReference(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $parts = parse_url($value);

            if (is_array($parts)) {
                $host = Str::lower($parts['host'] ?? '');
                $path = $parts['path'] ?? '';
                $query = isset($parts['query']) ? '?' . $parts['query'] : '';

                return trim($host . $path . $query);
            }
        }

        return Str::lower($value);
    }

    /**
     * Combines HTML/body class and head asset similarity into a single score.
     *
     * @param array{
     *     html_classes: array<int, string>,
     *     body_classes: array<int, string>,
     *     head_assets: array<int, string>
     * } $a
     * @param array{
     *     html_classes: array<int, string>,
     *     body_classes: array<int, string>,
     *     head_assets: array<int, string>
     * } $b
     */
    private function calculateSimilarity(array $a, array $b): float
    {
        $htmlClassSimilarity = $this->jaccardSimilarity($a['html_classes'] ?? [], $b['html_classes'] ?? []);
        $bodyClassSimilarity = $this->jaccardSimilarity($a['body_classes'] ?? [], $b['body_classes'] ?? []);
        $headAssetSimilarity = $this->jaccardSimilarity($a['head_assets'] ?? [], $b['head_assets'] ?? []);

        return ($headAssetSimilarity * 0.4) + ($htmlClassSimilarity * 0.3) + ($bodyClassSimilarity * 0.3);
    }

    /**
     * Calculates the Jaccard similarity between two sets of tokens.
     *
     * @param array<int, string> $a
     * @param array<int, string> $b
     */
    private function jaccardSimilarity(array $a, array $b): float
    {
        $setA = array_unique($a);
        $setB = array_unique($b);

        if (empty($setA) && empty($setB)) {
            return 1.0;
        }

        if (empty($setA) || empty($setB)) {
            return 0.0;
        }

        $intersection = array_intersect($setA, $setB);
        $union = array_unique(array_merge($setA, $setB));

        if (empty($union)) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }

    /**
     * Normalises snapshot fetch statuses into detector results when the request failed or returned nothing.
     */
    private function failureFromSnapshotFetch(array $fetchResult, string $noDataFallback, string $failureFallback): ?WebsiteRedesignDetectionResult
    {
        if ($fetchResult['status'] === WebsiteRedesignDetectionResult::STATUS_WAYBACK_FAILED) {
            return WebsiteRedesignDetectionResult::waybackFailure(
                $fetchResult['message'] ?? $failureFallback
            );
        }

        if ($fetchResult['status'] === WebsiteRedesignDetectionResult::STATUS_NO_WAYBACK_DATA) {
            return WebsiteRedesignDetectionResult::noWaybackData(
                $fetchResult['message'] ?? $noDataFallback
            );
        }

        return null;
    }

}
