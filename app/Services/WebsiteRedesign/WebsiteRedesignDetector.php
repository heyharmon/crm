<?php

namespace App\Services\WebsiteRedesign;

use App\Support\WebsiteRedesignDetectionResult;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebsiteRedesignDetector
{
    public function detect(?string $website): WebsiteRedesignDetectionResult
    {
        $snapshotResult = $this->fetchDistinctSnapshots($website);

        if ($snapshotResult['status'] === WebsiteRedesignDetectionResult::STATUS_WAYBACK_FAILED) {
            return WebsiteRedesignDetectionResult::waybackFailure(
                $snapshotResult['message'] ?? 'Wayback Machine request failed.'
            );
        }

        if ($snapshotResult['status'] === WebsiteRedesignDetectionResult::STATUS_NO_WAYBACK_DATA) {
            return WebsiteRedesignDetectionResult::noWaybackData(
                $snapshotResult['message'] ?? 'Wayback Machine did not return any snapshots for this website.'
            );
        }

        $events = $this->identifyMajorRedesigns($snapshotResult['snapshots']);

        if (empty($events)) {
            return WebsiteRedesignDetectionResult::noMajorEvents(
                'Wayback responded, but no redesign window met the persistence threshold.'
            );
        }

        return WebsiteRedesignDetectionResult::success($events);
    }

    /**
     * @return array{
     *     snapshots: array<int, array{timestamp: string, digest: string|null, captured_at: Carbon, payload_bytes: ?int}>,
     *     status: string,
     *     message: ?string
     * }
     */
    public function fetchDistinctSnapshots(?string $website): array
    {
        $normalized = $this->normalizeWebsite($website);
        if (!$normalized) {
            return [
                'snapshots' => [],
                'status' => WebsiteRedesignDetectionResult::STATUS_NO_WAYBACK_DATA,
                'message' => 'Website URL is missing or invalid.',
            ];
        }

        $timeoutSeconds = max(5, (int) config('waybackmachine.request_timeout_seconds', 45));

        try {
            $response = Http::timeout($timeoutSeconds)
                ->acceptJson()
                ->get(config('waybackmachine.cdx_endpoint'), [
                    'url' => $normalized,
                    'output' => 'json',
                    'fl' => 'timestamp,digest,statuscode,mimetype,length',
                    'collapse' => 'digest',
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Wayback CDX request failed', [
                'website' => $normalized,
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
                'website' => $normalized,
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
        $snapshots = $this->filterSnapshots(
            $rows,
            $this->allowedStatusCodes(),
            $this->allowedMimeTypes(),
            $this->minPayloadBytes()
        );

        if (empty($snapshots)) {
            return [
                'snapshots' => [],
                'status' => WebsiteRedesignDetectionResult::STATUS_NO_WAYBACK_DATA,
                'message' => 'Wayback snapshots were filtered out by the current filters.',
            ];
        }

        return [
            'snapshots' => $snapshots,
            'status' => WebsiteRedesignDetectionResult::STATUS_SUCCESS,
            'message' => null,
        ];
    }

    /**
     * @param array<int, array{timestamp: string, digest: string|null, captured_at: Carbon, payload_bytes: ?int}> $snapshots
     * @return array<int, array{
     *     timestamp: string,
     *     digest: string|null,
     *     captured_at: Carbon,
     *     persistence_days: int,
     *     median_payload_bytes: ?int,
     *     payload_change_ratio: ?float
     * }>
     */
    public function identifyMajorRedesigns(array $snapshots): array
    {
        if (empty($snapshots)) {
            return [];
        }

        $minPersistenceDays = max(1, (int) config('waybackmachine.min_persistence_days', 120));
        $maxEvents = max(1, (int) config('waybackmachine.max_events', 5));
        $minPayloadChangeRatio = max(0.0, (float) config('waybackmachine.min_payload_change_ratio', 0.3));
        $now = Carbon::now('UTC');
        $candidates = [];
        $previousMedianPayload = null;

        foreach ($snapshots as $index => $snapshot) {
            $current = $snapshot['captured_at'];
            $next = $snapshots[$index + 1]['captured_at'] ?? null;
            $stableUntil = $next ?? $now;
            $persistenceDays = $current->diffInDays($stableUntil);
            $medianPayload = $this->medianPayloadForWindow($snapshot);
            $changeRatio = null;

            if ($previousMedianPayload !== null && $medianPayload !== null) {
                $denominator = max($previousMedianPayload, 1);
                $changeRatio = abs($medianPayload - $previousMedianPayload) / $denominator;
            }

            if ($persistenceDays >= $minPersistenceDays) {
                $payloadShiftPasses = $previousMedianPayload === null
                    || $medianPayload === null
                    || $changeRatio === null
                    || $changeRatio >= $minPayloadChangeRatio;

                if (!$payloadShiftPasses) {
                    if ($medianPayload !== null) {
                        $previousMedianPayload = $medianPayload;
                    }
                    continue;
                }

                $candidates[] = [
                    'timestamp' => $snapshot['timestamp'],
                    'digest' => $snapshot['digest'],
                    'captured_at' => $current->copy(),
                    'persistence_days' => $persistenceDays,
                    'median_payload_bytes' => $medianPayload,
                    'payload_change_ratio' => $changeRatio,
                ];
            }

            if ($medianPayload !== null) {
                $previousMedianPayload = $medianPayload;
            }
        }

        if (count($candidates) > $maxEvents) {
            $candidates = array_slice($candidates, -$maxEvents);
        }

        return $candidates;
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

        $parts = parse_url($website);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        return $parts['host'];
    }

    /**
     * @return array<int>
     */
    private function allowedStatusCodes(): array
    {
        $codes = config('waybackmachine.allowed_status_codes');

        if (!is_array($codes) || empty($codes)) {
            return [200];
        }

        return array_values(array_filter(array_map(static function ($value) {
            $code = (int) $value;

            return $code > 0 ? $code : null;
        }, $codes)));
    }

    /**
     * @return array<int, string>
     */
    private function allowedMimeTypes(): array
    {
        $types = config('waybackmachine.allowed_mimetypes');

        if (!is_array($types) || empty($types)) {
            return ['text/html'];
        }

        return array_values(array_filter(array_map(static function ($value) {
            $type = Str::lower(trim((string) $value));

            return $type !== '' ? $type : null;
        }, $types)));
    }

    private function minPayloadBytes(): int
    {
        return max(0, (int) config('waybackmachine.min_payload_bytes', 10240));
    }

    /**
     * @param array<int, array{0:mixed,1:mixed,2:mixed,3:mixed,4:mixed}> $rows
     * @return array<int, array{timestamp: string, digest: string|null, captured_at: Carbon, payload_bytes: ?int}>
     */
    private function filterSnapshots(array $rows, array $allowedStatusCodes, array $allowedMimeTypes, int $minPayloadBytes): array
    {
        $snapshots = [];

        foreach ($rows as $row) {
            $timestamp = Arr::get($row, 0);
            $digest = Arr::get($row, 1);
            $statusCodeRaw = Arr::get($row, 2);
            $statusCode = is_numeric($statusCodeRaw) ? (int) $statusCodeRaw : null;
            $mimeType = Arr::get($row, 3);
            $payloadBytesRaw = Arr::get($row, 4);
            $payloadBytes = is_numeric($payloadBytesRaw) ? (int) $payloadBytesRaw : null;

            if (!$this->snapshotPassesFilters(
                $statusCode,
                is_string($mimeType) ? $mimeType : null,
                $payloadBytes,
                $allowedStatusCodes,
                $allowedMimeTypes,
                $minPayloadBytes
            )) {
                continue;
            }

            if (!is_string($timestamp) || $timestamp === '') {
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
                'digest' => is_string($digest) ? $digest : null,
                'captured_at' => $capturedAt,
                'payload_bytes' => $payloadBytes,
            ];
        }

        usort($snapshots, fn($a, $b) => $a['captured_at'] <=> $b['captured_at']);

        return $snapshots;
    }

    private function snapshotPassesFilters(
        ?int $statusCode,
        ?string $mimeType,
        ?int $payloadBytes,
        array $allowedStatusCodes,
        array $allowedMimeTypes,
        int $minPayloadBytes
    ): bool {
        if (!empty($allowedStatusCodes) && ($statusCode === null || !in_array($statusCode, $allowedStatusCodes, true))) {
            return false;
        }

        if (!empty($allowedMimeTypes)) {
            if ($mimeType === null) {
                return false;
            }

            if (!in_array(Str::lower($mimeType), $allowedMimeTypes, true)) {
                return false;
            }
        }

        if ($minPayloadBytes > 0) {
            if ($payloadBytes === null || $payloadBytes < $minPayloadBytes) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{payload_bytes: ?int} $snapshot
     */
    private function medianPayloadForWindow(array $snapshot): ?int
    {
        $payloadBytes = $snapshot['payload_bytes'] ?? null;

        if ($payloadBytes === null) {
            return null;
        }

        return $payloadBytes;
    }
}
