<?php

namespace App\Jobs;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that fetches redesign analysis from Design Decoder API
 * and updates the organization's redesign data.
 */
class DetectWebsiteRedesignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

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

        $apiUrl = config('designdecoder.api_url');

        if (!$apiUrl) {
            Log::error('Design Decoder API URL not configured');
            $this->updateOrganizationStatus($organization, 'api_failed', 'Design Decoder API URL not configured');
            return;
        }

        try {
            $response = Http::timeout(config('designdecoder.timeout', 300))
                ->get($apiUrl . '/api/analyze', [
                    'url' => $organization->website,
                ]);

            if (!$response->successful()) {
                $errorMessage = $response->json('error') ?? 'API request failed';
                Log::error('Design Decoder API request failed', [
                    'organization_id' => $this->organizationId,
                    'status' => $response->status(),
                    'error' => $errorMessage,
                ]);
                $this->updateOrganizationStatus($organization, 'api_failed', $errorMessage);
                return;
            }

            $data = $response->json();

            if (!isset($data['data']) || !is_array($data['data'])) {
                Log::error('Invalid API response structure', [
                    'organization_id' => $this->organizationId,
                ]);
                $this->updateOrganizationStatus($organization, 'api_failed', 'Invalid API response structure');
                return;
            }

            $this->processAnalysisResult($organization, $data);
        } catch (\Exception $e) {
            Log::error('Design Decoder API request exception', [
                'organization_id' => $this->organizationId,
                'error' => $e->getMessage(),
            ]);
            $this->updateOrganizationStatus($organization, 'api_failed', 'API request failed: ' . $e->getMessage());
        }
    }

    private function processAnalysisResult(Organization $organization, array $data): void
    {
        DB::transaction(function () use ($organization, $data) {
            // Clear existing redesign records
            $organization->websiteRedesigns()->delete();

            // The API returns a single predicted redesign event
            if (isset($data['predictedTimestamp']) && isset($data['beforeTimestamp'])) {
                $organization->websiteRedesigns()->create([
                    'before_wayback_timestamp' => $data['beforeTimestamp'],
                    'before_captured_at' => $this->parseWaybackTimestamp($data['beforeTimestamp']),
                    'after_wayback_timestamp' => $data['predictedTimestamp'],
                    'after_captured_at' => $this->parseWaybackTimestamp($data['predictedTimestamp']),
                    'nav_similarity' => null,
                    'before_html_class_count' => null,
                    'after_html_class_count' => null,
                    'before_body_class_count' => null,
                    'after_body_class_count' => null,
                    'before_html_classes' => [],
                    'after_html_classes' => [],
                    'before_body_classes' => [],
                    'after_body_classes' => [],
                ]);

                $organization->forceFill([
                    'last_major_redesign_at' => $this->parseWaybackTimestamp($data['predictedTimestamp']),
                    'website_redesign_status' => 'success',
                    'website_redesign_status_message' => null,
                ])->save();
            } else {
                // No redesign detected
                $organization->forceFill([
                    'last_major_redesign_at' => null,
                    'website_redesign_status' => 'no_major_events',
                    'website_redesign_status_message' => 'No major redesigns detected',
                ])->save();
            }
        });
    }

    private function updateOrganizationStatus(Organization $organization, string $status, ?string $message = null): void
    {
        $organization->forceFill([
            'website_redesign_status' => $status,
            'website_redesign_status_message' => $message,
        ])->save();
    }

    private function parseWaybackTimestamp(string $timestamp): ?\Carbon\Carbon
    {
        try {
            // Wayback timestamps are in format: YYYYMMDDHHmmss
            return \Carbon\Carbon::createFromFormat('YmdHis', $timestamp);
        } catch (\Exception $e) {
            return null;
        }
    }
}
