<?php

namespace App\Services\WebsiteRedesign;

use App\Support\WebsiteRedesignDetectionResult;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Detects website redesigns by comparing navigation structure across Wayback snapshots.
 *
 * Workflow:
 *  - collect coarse yearly snapshots and build navigation signatures
 *  - flag large signature changes to highlight candidate redesign windows
 *  - drill into monthly snapshots to find the first capture that reflects the new navigation
 */
class WebsiteRedesignDetector
{
    private const WAYBACK_USER_AGENT = 'HerdCRM/WaybackDetector';

    /**
     * @var array<string, array|null>
     */
    private array $navSignatureCache = [];

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

        // Build coarse timeline of nav signatures using yearly snapshots.
        $yearlyTimeline = $this->buildSignatureTimeline($normalized, $yearlySnapshots['snapshots']);

        // Identify the windows where the nav changed enough to investigate further.
        $changeWindows = $this->detectChangeWindows($yearlyTimeline);
        if (empty($changeWindows)) {
            return WebsiteRedesignDetectionResult::noMajorEvents(
                'Navigation analysis did not detect any major redesigns.'
            );
        }

        $events = [];
        foreach ($changeWindows as $window) {
            // Drill into monthly snapshots to pinpoint the first capture of the new navigation.
            $event = $this->refineChangeWindow($normalized, $window);

            if ($event !== null) {
                $events[] = $event;
            }
        }

        if (empty($events)) {
            return WebsiteRedesignDetectionResult::noMajorEvents(
                'Unable to pinpoint a redesign window after refining navigation changes.'
            );
        }

        $maxEvents = max(1, (int) config('waybackmachine.max_events', 5));
        if (count($events) > $maxEvents) {
            $events = array_slice($events, -$maxEvents);
        }

        return WebsiteRedesignDetectionResult::success($events);
    }

    /**
     * Builds a chronological list of snapshots enriched with their extracted navigation signature.
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
                'signature' => $this->getNavSignatureForTimestamp($host, $snapshot['timestamp']),
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
        $threshold = $this->navChangeThreshold();
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
     * Zooms into the coarse window and finds the first monthly snapshot that matches the new navigation.
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

        $matchThreshold = $this->navMatchThreshold();
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
     *     before_nav_class_count: ?int,
     *     after_nav_class_count: ?int,
     *     before_nav_classes: array<int, string>,
     *     after_nav_classes: array<int, string>,
     *     before_nav_html: ?string,
     *     after_nav_html: ?string
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
            'before_nav_class_count' => $previousSignature['class_count'] ?? null,
            'after_nav_class_count' => $afterSignature['class_count'] ?? null,
            'before_nav_classes' => $previousSignature['classes'] ?? [],
            'after_nav_classes' => $afterSignature['classes'] ?? [],
            'before_nav_html' => $previousSignature['html'] ?? null,
            'after_nav_html' => $afterSignature['html'] ?? null,
        ];
    }

    /**
     * Fetches a collapsed set of yearly snapshots to get a broad view of navigation changes.
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
     * Minimum similarity score required for two nav signatures to be considered different enough.
     */
    private function navChangeThreshold(): float
    {
        $value = (float) config('waybackmachine.nav_similarity_change_threshold', 0.6);

        return max(0.0, min(1.0, $value));
    }

    /**
     * Similarity threshold used when confirming the first snapshot of the new design.
     */
    private function navMatchThreshold(): float
    {
        $value = (float) config('waybackmachine.nav_similarity_match_threshold', 0.75);

        return max(0.0, min(1.0, $value));
    }

    /**
     * Retrieves (or caches) the navigation signature for the given snapshot timestamp.
     *
     * @return array{
     *     hash: string,
     *     structure: string,
     *     class_tokens: array<int, string>,
     *     classes: array<int, string>,
     *     class_count: int,
     *     html: string
     * }|null
     */
    private function getNavSignatureForTimestamp(string $host, string $timestamp): ?array
    {
        if (array_key_exists($timestamp, $this->navSignatureCache)) {
            return $this->navSignatureCache[$timestamp];
        }

        $html = $this->fetchSnapshotHtml($host, $timestamp);

        if ($html === null) {
            $this->navSignatureCache[$timestamp] = null;

            return null;
        }

        $signature = $this->extractNavSignature($html);

        $this->navSignatureCache[$timestamp] = $signature;

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
     * Converts the raw nav DOM into a lightweight signature we can compare.
     *
     * @return array{
     *     hash: string,
     *     structure: string,
     *     class_tokens: array<int, string>,
     *     classes: array<int, string>,
     *     class_count: int,
     *     html: string
     * }|null
     */
    private function extractNavSignature(string $html): ?array
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);

        if (!$dom->loadHTML($html)) {
            libxml_clear_errors();

            return null;
        }

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $navNode = $this->selectBestNavNode($xpath);

        if (!$navNode) {
            return null;
        }

        $structure = $this->collectStructureSignature($navNode);
        $classData = $this->collectCssClassSignature($navNode);

        $signatureBasis = $structure . '|' . implode('|', $classData['tokens']);

        return [
            'hash' => sha1($signatureBasis),
            'structure' => $structure,
            'class_tokens' => $classData['tokens'],
            'classes' => $classData['classes'],
            'class_count' => $classData['count'],
            'html' => trim($dom->saveHTML($navNode) ?: ''),
        ];
    }

    /**
     * Picks the most representative navigation node from the DOM.
     */
    private function selectBestNavNode(DOMXPath $xpath): ?DOMElement
    {
        $candidates = [];

        $navNodes = $xpath->query('//nav');
        if ($navNodes !== false) {
            foreach ($navNodes as $node) {
                if ($node instanceof DOMElement) {
                    $candidates[] = $node;
                }
            }
        }

        if (empty($candidates)) {
            $roleNodes = $xpath->query('//*[@role="navigation"]');
            if ($roleNodes !== false) {
                foreach ($roleNodes as $node) {
                    if ($node instanceof DOMElement) {
                        $candidates[] = $node;
                    }
                }
            }
        }

        if (empty($candidates)) {
            $classNodes = $xpath->query('//*[@id[contains(translate(., "NAV", "nav"), "nav")] or contains(translate(@class, "NAV", "nav"), "nav")]');
            if ($classNodes !== false) {
                foreach ($classNodes as $node) {
                    if ($node instanceof DOMElement) {
                        $candidates[] = $node;
                    }
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        $bestNode = null;
        $bestScore = -INF;

        foreach ($candidates as $candidate) {
            $anchorCount = $candidate->getElementsByTagName('a')->length;
            $html = $candidate->ownerDocument?->saveHTML($candidate) ?? '';
            $score = ($anchorCount * 10) + strlen($html);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestNode = $candidate;
            }
        }

        return $bestNode;
    }

    /**
     * Produces a depth-annotated string describing the nav DOM hierarchy.
     */
    private function collectStructureSignature(DOMElement $element): string
    {
        $segments = [];

        $this->walkDom($element, static function (DOMElement $node, int $depth) use (&$segments) {
            $segments[] = str_repeat('.', $depth) . Str::lower($node->tagName);
        });

        return implode('|', $segments);
    }

    /**
     * Collects normalized CSS class fingerprints for every element inside the navigation node.
     */
    private function collectCssClassSignature(DOMElement $element): array
    {
        $tokens = [];
        $fallbackTokens = [];
        $classes = [];

        $this->walkDom($element, function (DOMElement $node, int $depth) use (&$tokens, &$fallbackTokens, &$classes) {
            $classAttribute = trim((string) $node->getAttribute('class'));
            $normalizedTag = Str::lower($node->tagName);
            $normalizedClasses = [];

            if ($classAttribute !== '') {
                foreach (preg_split('/\s+/', $classAttribute) as $rawClass) {
                    $cleaned = trim($rawClass);
                    if ($cleaned === '') {
                        continue;
                    }

                    $normalized = Str::lower($cleaned);
                    $classes[] = $normalized;
                    $normalizedClasses[] = $normalized;
                }
            }

            sort($normalizedClasses);

            if (!empty($normalizedClasses)) {
                $tokenParts = [sprintf('%02d', max(0, $depth)), $normalizedTag, implode('.', $normalizedClasses)];
                $tokens[] = implode('|', $tokenParts);
            } else {
                $fallbackTokens[] = implode('|', [sprintf('%02d', max(0, $depth)), $normalizedTag]);
            }
        });

        if (empty($tokens)) {
            $tokens = $fallbackTokens;
        }

        $uniqueTokens = array_values(array_unique($tokens));
        sort($uniqueTokens);

        $uniqueClasses = array_values(array_unique($classes));
        sort($uniqueClasses);

        return [
            'tokens' => $uniqueTokens,
            'classes' => $uniqueClasses,
            'count' => count($uniqueClasses),
        ];
    }

    /**
     * Depth-first traversal helper used when building structure signatures.
     */
    private function walkDom(DOMNode $node, callable $callback, int $depth = 0): void
    {
        if ($node instanceof DOMElement) {
            $callback($node, $depth);
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $this->walkDom($child, $callback, $depth + 1);
            }
        }
    }

    /**
     * Combines structural and CSS class similarity into a single score.
     *
     * @param array{
     *     structure: string,
     *     class_tokens: array<int, string>,
     *     classes: array<int, string>
     * } $a
     * @param array{
     *     structure: string,
     *     class_tokens: array<int, string>,
     *     classes: array<int, string>
     * } $b
     */
    private function calculateSimilarity(array $a, array $b): float
    {
        $structureSimilarity = $this->stringSimilarity($a['structure'] ?? '', $b['structure'] ?? '');
        $classTokenSimilarity = $this->jaccardSimilarity($a['class_tokens'] ?? [], $b['class_tokens'] ?? []);
        $classSimilarity = $this->jaccardSimilarity($a['classes'] ?? [], $b['classes'] ?? []);

        return ($classTokenSimilarity * 0.5) + ($classSimilarity * 0.3) + ($structureSimilarity * 0.2);
    }

    /**
     * Wrapper around `similar_text` that outputs a 0-1 score for structure strings.
     */
    private function stringSimilarity(string $a, string $b): float
    {
        if ($a === '' && $b === '') {
            return 1.0;
        }

        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return max(0.0, min(1.0, $percent / 100));
    }

    /**
     * Calculates the Jaccard similarity between two sets of navigation class tokens.
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
