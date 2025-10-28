<?php

namespace App\Services\WebsiteRedesign;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class WebsiteRedesignService
{
    public function __construct(private WebsiteRedesignDetector $detector) {}

    /**
     * Pulls the latest redesign information from Wayback and refreshes the persisted snapshot pair.
     */
    public function refreshOrganizationRedesigns(Organization $organization): void
    {
        $this->throttleWaybackRequests();

        $result = $this->detector->detect($organization->website);

        DB::transaction(function () use ($organization, $result) {
            // Always rebuild the redesign history so the database mirrors the detector output.
            $organization->websiteRedesigns()->delete();

            foreach ($result->events as $event) {
                $organization->websiteRedesigns()->create([
                    'before_wayback_timestamp' => $event['before_timestamp'],
                    'before_captured_at' => $event['before_captured_at'] ?? null,
                    'after_wayback_timestamp' => $event['after_timestamp'],
                    'after_captured_at' => $event['after_captured_at'] ?? null,
                    'nav_similarity' => $event['nav_similarity'] ?? null,
                    'before_html_class_count' => $event['before_html_class_count'] ?? null,
                    'after_html_class_count' => $event['after_html_class_count'] ?? null,
                    'before_body_class_count' => $event['before_body_class_count'] ?? null,
                    'after_body_class_count' => $event['after_body_class_count'] ?? null,
                    'before_html_classes' => $event['before_html_classes'] ?? [],
                    'after_html_classes' => $event['after_html_classes'] ?? [],
                    'before_body_classes' => $event['before_body_classes'] ?? [],
                    'after_body_classes' => $event['after_body_classes'] ?? [],
                ]);
            }

            $lastEvent = empty($result->events) ? null : $result->events[count($result->events) - 1];

            $organization->forceFill([
                'last_major_redesign_at' => $lastEvent['after_captured_at'] ?? null,
                'website_redesign_status' => $result->status,
                'website_redesign_status_message' => $result->message,
            ])->save();
        });
    }

    /**
     * Simple rate limiter so queued jobs do not overwhelm the archive.
     */
    private function throttleWaybackRequests(): void
    {
        $delayMs = max(0, (int) config('waybackmachine.request_delay_ms', 1000));
        if ($delayMs === 0) {
            return;
        }

        usleep($delayMs * 1000);
    }
}
