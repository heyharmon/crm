<?php

namespace App\Jobs;

use App\Models\Organization;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analyzes website redesigns by fetching historical snapshots from Wayback Machine
 * and detecting major design changes through statistical analysis.
 */
class DetectWebsiteRedesignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600; // 1 hour for extensive analysis

    public function __construct(private int $organizationId) {}

    public function handle(): void
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

        try {
            // Step 1: Fetch and sample snapshots from Wayback Machine
            $snapshots = $this->fetchAndSampleSnapshots($organization->website);

            if (empty($snapshots)) {
                $this->updateOrganizationStatus($organization, 'no_major_events', 'No historical snapshots found');
                return;
            }

            if (count($snapshots) < 2) {
                $this->updateOrganizationStatus($organization, 'no_major_events', 'Insufficient snapshots for analysis');
                return;
            }

            // Step 2-4: Extract features, calculate differences, and analyze
            $analysisData = $this->analyzeSnapshots($snapshots);

            if (empty($analysisData)) {
                $this->updateOrganizationStatus($organization, 'no_major_events', 'Unable to analyze snapshots');
                return;
            }

            // Step 5: Predict redesign
            $this->predictAndStoreRedesign($organization, $analysisData);
        } catch (\Exception $e) {
            Log::error('Website redesign analysis failed', [
                'organization_id' => $this->organizationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->updateOrganizationStatus($organization, 'api_failed', 'Analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch snapshots from Wayback Machine CDX API and sample to one per 6-month period
     */
    private function fetchAndSampleSnapshots(string $url): array
    {
        // Extract domain from URL (remove protocol and www)
        $domain = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $url);
        $domain = explode('/', $domain)[0];

        // Query CDX API for all successful HTML captures
        $response = Http::timeout(60)->get('https://web.archive.org/cdx/search/cdx', [
            'url' => $domain,
            'output' => 'json',
            'fl' => 'timestamp,original',
            'filter' => ['statuscode:200', 'mimetype:text/html'],
            'collapse' => 'timestamp:6', // Collapse to one per month
            'limit' => 500,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch snapshots from Wayback Machine');
        }

        $data = $response->json();

        if (empty($data) || !is_array($data)) {
            return [];
        }

        // Remove header row if present
        if (isset($data[0]) && $data[0][0] === 'timestamp') {
            array_shift($data);
        }

        // Sample to one snapshot per 6-month period
        return $this->sampleToSixMonthPeriods($data);
    }

    /**
     * Sample snapshots to one per 6-month period (H1: Jan-Jun, H2: Jul-Dec)
     * Limited to the last 10 years from today
     */
    private function sampleToSixMonthPeriods(array $snapshots): array
    {
        $sampled = [];
        $seenPeriods = [];

        // Calculate cutoff date (10 years ago from today)
        $tenYearsAgo = Carbon::now()->subYears(10);
        $cutoffTimestamp = $tenYearsAgo->format('YmdHis');

        foreach ($snapshots as $snapshot) {
            if (!isset($snapshot[0])) {
                continue;
            }

            $timestamp = $snapshot[0];

            // Skip snapshots older than 10 years
            if ($timestamp < $cutoffTimestamp) {
                continue;
            }

            $year = substr($timestamp, 0, 4);
            $month = (int) substr($timestamp, 4, 2);

            // Determine period (H1 or H2)
            $period = $year . ($month <= 6 ? '-H1' : '-H2');

            if (!in_array($period, $seenPeriods)) {
                $sampled[] = [
                    'timestamp' => $timestamp,
                    'url' => $snapshot[1] ?? $snapshot[0],
                ];
                $seenPeriods[] = $period;
            }
        }

        return $sampled;
    }

    /**
     * Analyze all snapshots: extract features, calculate differences, compute statistics
     */
    private function analyzeSnapshots(array $snapshots): array
    {
        $analysisData = [];

        // Process pairs of consecutive snapshots
        for ($i = 0; $i < count($snapshots) - 1; $i++) {
            $snapshot1 = $snapshots[$i];
            $snapshot2 = $snapshots[$i + 1];

            // Extract features from both snapshots
            $features1 = $this->extractFeatures($snapshot1['timestamp'], $snapshot1['url']);
            $features2 = $this->extractFeatures($snapshot2['timestamp'], $snapshot2['url']);

            if (!$features1 || !$features2) {
                continue;
            }

            // Calculate difference scores
            $tagScore = $this->calculateDifferenceScore(
                $features1['tagCounts'],
                $features2['tagCounts']
            );

            $classScore = $this->calculateDifferenceScore(
                $features1['classCounts'],
                $features2['classCounts']
            );

            $assetScore = $this->calculateJaccardDistance(
                $features1['assetUrls'],
                $features2['assetUrls']
            );

            // Composite score: weighted average (classes 50%, tags 25%, assets 25%)
            $compositeScore = ($tagScore * 0.25) + ($classScore * 0.50) + ($assetScore * 0.25);

            $analysisData[] = [
                'before_timestamp' => $snapshot1['timestamp'],
                'after_timestamp' => $snapshot2['timestamp'],
                'before_features' => $features1,
                'after_features' => $features2,
                'tag_score' => $tagScore,
                'class_score' => $classScore,
                'asset_score' => $assetScore,
                'composite_score' => $compositeScore,
            ];
        }

        return $analysisData;
    }

    /**
     * Extract HTML features from a Wayback Machine snapshot
     */
    private function extractFeatures(string $timestamp, string $originalUrl): ?array
    {
        $maxRetries = 3;
        $retryDelay = 5; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Add delay between requests to avoid rate limiting (except first request)
                if ($attempt > 1) {
                    sleep($retryDelay);
                }

                // Use if_ flag to get the page without Wayback's modifications
                $waybackUrl = "https://web.archive.org/web/{$timestamp}if_/{$originalUrl}";

                $response = Http::timeout(30)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; RedesignDetector/1.0)',
                    ])
                    ->get($waybackUrl);

                if (!$response->successful()) {
                    if ($attempt < $maxRetries) {
                        continue;
                    }
                    return null;
                }

                $html = $response->body();

                // Parse HTML
                $dom = new DOMDocument();
                @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

                // Extract tag counts from body
                $tagCounts = $this->extractTagCounts($dom);

                // Extract CSS class counts from body
                $classCounts = $this->extractClassCounts($dom);

                // Extract head asset URLs
                $assetUrls = $this->extractHeadAssetUrls($dom);

                // Add small delay between successful requests to be respectful
                usleep(500000); // 0.5 seconds

                return [
                    'tagCounts' => $tagCounts,
                    'classCounts' => $classCounts,
                    'assetUrls' => $assetUrls,
                ];
            } catch (\Exception $e) {
                if ($attempt === $maxRetries) {
                    Log::warning('Failed to extract features from snapshot after retries', [
                        'timestamp' => $timestamp,
                        'url' => $originalUrl,
                        'attempts' => $maxRetries,
                        'error' => $e->getMessage(),
                    ]);
                    return null;
                }
                // Continue to next retry
            }
        }

        return null;
    }

    /**
     * Extract frequency map of HTML tags in body
     */
    private function extractTagCounts(DOMDocument $dom): array
    {
        $counts = [];

        if (!$dom->documentElement || !$dom->getElementsByTagName('body')->item(0)) {
            return $counts;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $elements = $body->getElementsByTagName('*');

        foreach ($elements as $element) {
            $tagName = strtolower($element->tagName);
            $counts[$tagName] = ($counts[$tagName] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Extract frequency map of CSS classes in body
     */
    private function extractClassCounts(DOMDocument $dom): array
    {
        $counts = [];

        if (!$dom->documentElement || !$dom->getElementsByTagName('body')->item(0)) {
            return $counts;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $elements = $body->getElementsByTagName('*');

        foreach ($elements as $element) {
            $classList = $element->getAttribute('class');
            if ($classList) {
                $classes = preg_split('/\s+/', $classList);
                foreach ($classes as $class) {
                    $class = trim($class);
                    if ($class !== '') {
                        $counts[$class] = ($counts[$class] ?? 0) + 1;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * Extract unique URLs of stylesheets and scripts from head
     */
    private function extractHeadAssetUrls(DOMDocument $dom): array
    {
        $urls = [];

        if (!$dom->documentElement || !$dom->getElementsByTagName('head')->item(0)) {
            return $urls;
        }

        $head = $dom->getElementsByTagName('head')->item(0);

        // Stylesheets
        $stylesheets = $head->getElementsByTagName('link');
        foreach ($stylesheets as $link) {
            if ($link->getAttribute('rel') === 'stylesheet') {
                $href = $link->getAttribute('href');
                if ($href) {
                    $urls[] = $href;
                }
            }
        }

        // Scripts
        $scripts = $head->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $src = $script->getAttribute('src');
            if ($src) {
                $urls[] = $src;
            }
        }

        return array_unique($urls);
    }

    /**
     * Calculate normalized difference score (Bray-Curtis variation)
     * Returns value between 0 (identical) and 1 (completely different)
     */
    private function calculateDifferenceScore(array $counts1, array $counts2): float
    {
        $allKeys = array_unique(array_merge(array_keys($counts1), array_keys($counts2)));

        $absoluteDifferenceSum = 0;
        $totalSum = 0;

        foreach ($allKeys as $key) {
            $val1 = $counts1[$key] ?? 0;
            $val2 = $counts2[$key] ?? 0;
            $absoluteDifferenceSum += abs($val1 - $val2);
            $totalSum += $val1 + $val2;
        }

        if ($totalSum == 0) {
            return 0;
        }

        return $absoluteDifferenceSum / $totalSum;
    }

    /**
     * Calculate Jaccard distance for sets
     * Returns value between 0 (identical) and 1 (no overlap)
     */
    private function calculateJaccardDistance(array $set1, array $set2): float
    {
        $intersection = array_intersect($set1, $set2);
        $union = array_unique(array_merge($set1, $set2));

        $intersectionSize = count($intersection);
        $unionSize = count($union);

        if ($unionSize == 0) {
            return 0;
        }

        return 1 - ($intersectionSize / $unionSize);
    }

    /**
     * Predict redesign using statistical analysis and store results
     */
    private function predictAndStoreRedesign(Organization $organization, array $analysisData): void
    {
        // Sort analysis data by timestamp (ascending)
        usort($analysisData, function ($a, $b) {
            return strcmp($a['after_timestamp'], $b['after_timestamp']);
        });

        // Calculate statistical baseline
        $compositeScores = array_column($analysisData, 'composite_score');
        $mean = $this->calculateMean($compositeScores);
        $stdDev = $this->calculateStandardDeviation($compositeScores, $mean);
        $threshold = $mean + $stdDev;

        Log::info('Redesign detection statistics', [
            'organization_id' => $organization->id,
            'total_comparisons' => count($analysisData),
            'mean' => $mean,
            'stdDev' => $stdDev,
            'threshold' => $threshold,
            'scores' => array_map(function ($d) {
                return [
                    'timestamp' => $d['after_timestamp'],
                    'score' => $d['composite_score'],
                ];
            }, $analysisData),
        ]);

        // Find all major redesigns (scores above threshold)
        $majorRedesigns = array_filter($analysisData, function ($data) use ($threshold) {
            return $data['composite_score'] > $threshold;
        });

        // Reset array keys after filter
        $majorRedesigns = array_values($majorRedesigns);

        Log::info('Major redesigns found', [
            'organization_id' => $organization->id,
            'count' => count($majorRedesigns),
            'redesigns' => array_map(function ($d) {
                return [
                    'timestamp' => $d['after_timestamp'],
                    'score' => $d['composite_score'],
                ];
            }, $majorRedesigns),
        ]);

        // Select the most recent redesign, or fallback to highest score
        if (!empty($majorRedesigns)) {
            // Get the latest redesign (last in chronological order)
            $predictedRedesign = end($majorRedesigns);
        } else {
            // Fallback: use the period with highest change score
            $predictedRedesign = $analysisData[0];
            foreach ($analysisData as $data) {
                if ($data['composite_score'] > $predictedRedesign['composite_score']) {
                    $predictedRedesign = $data;
                }
            }
        }

        Log::info('Predicted redesign', [
            'organization_id' => $organization->id,
            'timestamp' => $predictedRedesign['after_timestamp'],
            'score' => $predictedRedesign['composite_score'],
        ]);

        // Store the redesign event
        DB::transaction(function () use ($organization, $predictedRedesign, $threshold) {
            // Clear existing redesign records
            $organization->websiteRedesigns()->delete();

            $beforeFeatures = $predictedRedesign['before_features'];
            $afterFeatures = $predictedRedesign['after_features'];

            // Create redesign record
            $organization->websiteRedesigns()->create([
                'before_wayback_timestamp' => $predictedRedesign['before_timestamp'],
                'before_captured_at' => $this->parseWaybackTimestamp($predictedRedesign['before_timestamp']),
                'after_wayback_timestamp' => $predictedRedesign['after_timestamp'],
                'after_captured_at' => $this->parseWaybackTimestamp($predictedRedesign['after_timestamp']),
                'tag_difference_score' => $predictedRedesign['tag_score'],
                'class_difference_score' => $predictedRedesign['class_score'],
                'asset_difference_score' => $predictedRedesign['asset_score'],
                'composite_score' => $predictedRedesign['composite_score'],
                'statistical_threshold' => $threshold,
                'before_tag_counts' => $beforeFeatures['tagCounts'],
                'after_tag_counts' => $afterFeatures['tagCounts'],
                'before_html_class_count' => count($beforeFeatures['classCounts']),
                'after_html_class_count' => count($afterFeatures['classCounts']),
                'before_body_class_count' => array_sum($beforeFeatures['classCounts']),
                'after_body_class_count' => array_sum($afterFeatures['classCounts']),
                'before_head_asset_count' => count($beforeFeatures['assetUrls']),
                'after_head_asset_count' => count($afterFeatures['assetUrls']),
                'before_html_classes' => array_keys($beforeFeatures['classCounts']),
                'after_html_classes' => array_keys($afterFeatures['classCounts']),
                'before_body_classes' => array_keys($beforeFeatures['classCounts']),
                'after_body_classes' => array_keys($afterFeatures['classCounts']),
                'before_head_assets' => $beforeFeatures['assetUrls'],
                'after_head_assets' => $afterFeatures['assetUrls'],
                'nav_similarity' => null,
                'before_head_html' => null,
                'after_head_html' => null,
            ]);

            // Update organization
            $organization->forceFill([
                'last_major_redesign_at' => $this->parseWaybackTimestamp($predictedRedesign['after_timestamp']),
                'website_redesign_status' => 'success',
                'website_redesign_status_message' => null,
            ])->save();
        });
    }

    /**
     * Calculate mean of an array of numbers
     */
    private function calculateMean(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * Calculate standard deviation
     */
    private function calculateStandardDeviation(array $values, float $mean): float
    {
        if (empty($values)) {
            return 0;
        }

        $squaredDifferences = array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);

        return sqrt(array_sum($squaredDifferences) / count($values));
    }

    /**
     * Update organization status
     */
    private function updateOrganizationStatus(Organization $organization, string $status, ?string $message = null): void
    {
        $organization->forceFill([
            'website_redesign_status' => $status,
            'website_redesign_status_message' => $message,
        ])->save();
    }

    /**
     * Parse Wayback timestamp to Carbon instance
     */
    private function parseWaybackTimestamp(string $timestamp): ?Carbon
    {
        try {
            return Carbon::createFromFormat('YmdHis', $timestamp);
        } catch (\Exception $e) {
            return null;
        }
    }
}
