<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebsiteRedesignDetector
{
    public function detect(string $website): array
    {
        $snapshots = $this->fetchDistinctSnapshots($website);

        if (empty($snapshots)) {
            return [];
        }

        return $this->identifyMajorRedesigns($snapshots);
    }

    /**
     * @return array<int, array{timestamp: string, digest: string|null, captured_at: Carbon}>
     */
    public function fetchDistinctSnapshots(string $website): array
    {
        $normalized = $this->normalizeWebsite($website);
        if (!$normalized) {
            Log::info('Unable to normalize website for redesign detection', [
                'website' => $website,
            ]);
            return [];
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get(config('redesign.cdx_endpoint'), [
                    'url' => $normalized,
                    'output' => 'json',
                    'fl' => 'timestamp,digest',
                    'collapse' => 'digest',
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Wayback CDX request failed', [
                'website' => $normalized,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }

        if (!$response->successful()) {
            Log::warning('Wayback CDX response returned non-200', [
                'website' => $normalized,
                'status' => $response->status(),
            ]);
            return [];
        }

        $payload = $response->json();
        if (!is_array($payload) || count($payload) <= 1) {
            return [];
        }

        $rows = array_slice($payload, 1);
        $snapshots = [];

        foreach ($rows as $row) {
            $timestamp = Arr::get($row, 0);
            $digest = Arr::get($row, 1);

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
            ];
        }

        usort($snapshots, fn ($a, $b) => $a['captured_at'] <=> $b['captured_at']);

        return $snapshots;
    }

    /**
     * @param array<int, array{timestamp: string, digest: string|null, captured_at: Carbon}> $snapshots
     * @return array<int, array{timestamp: string, digest: string|null, captured_at: Carbon, persistence_days: int}>
     */
    public function identifyMajorRedesigns(array $snapshots): array
    {
        if (empty($snapshots)) {
            return [];
        }

        $minPersistenceDays = max(1, (int) config('redesign.min_persistence_days', 120));
        $maxEvents = max(1, (int) config('redesign.max_events', 5));
        $now = Carbon::now('UTC');
        $candidates = [];

        foreach ($snapshots as $index => $snapshot) {
            $current = $snapshot['captured_at'];
            $next = $snapshots[$index + 1]['captured_at'] ?? null;
            $stableUntil = $next ?? $now;
            $persistenceDays = $current->diffInDays($stableUntil);

            if ($persistenceDays >= $minPersistenceDays) {
                $candidates[] = [
                    'timestamp' => $snapshot['timestamp'],
                    'digest' => $snapshot['digest'],
                    'captured_at' => $current->copy(),
                    'persistence_days' => $persistenceDays,
                ];
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
}
