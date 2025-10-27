<?php

namespace App\Services\WebsiteRedesign;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class WebsiteRedesignService
{
    public function __construct(private WebsiteRedesignDetector $detector) {}

    public function refreshOrganizationRedesigns(Organization $organization): void
    {
        $this->throttleWaybackRequests();

        if ($organization->last_major_redesign_at || $organization->website_redesign_status) {
            DB::transaction(function () use ($organization) {
                $organization->websiteRedesigns()->delete();
                $organization->last_major_redesign_at = null;
                $organization->website_redesign_status = null;
                $organization->website_redesign_status_message = null;
                $organization->save();
            });
        }

        $result = $this->detector->detect($organization->website);

        DB::transaction(function () use ($organization, $result) {
            $organization->websiteRedesigns()->delete();

            foreach ($result->events as $event) {
                $organization->websiteRedesigns()->create([
                    'before_wayback_timestamp' => $event['before_timestamp'],
                    'before_captured_at' => $event['before_captured_at'] ?? null,
                    'after_wayback_timestamp' => $event['after_timestamp'],
                    'after_captured_at' => $event['after_captured_at'] ?? null,
                    'nav_similarity' => $event['nav_similarity'] ?? null,
                    'before_nav_link_count' => $event['before_nav_link_count'] ?? null,
                    'after_nav_link_count' => $event['after_nav_link_count'] ?? null,
                    'before_nav_links' => $event['before_nav_links'] ?? [],
                    'after_nav_links' => $event['after_nav_links'] ?? [],
                    'before_nav_html' => $event['before_nav_html'] ?? null,
                    'after_nav_html' => $event['after_nav_html'] ?? null,
                ]);
            }

            $lastEvent = empty($result->events) ? null : $result->events[count($result->events) - 1];
            $organization->last_major_redesign_at = $lastEvent['after_captured_at'] ?? null;
            $organization->website_redesign_status = $result->status;
            $organization->website_redesign_status_message = $result->message;
            $organization->save();
        });
    }

    private function throttleWaybackRequests(): void
    {
        $delayMs = max(0, (int) config('waybackmachine.request_delay_ms', 1000));
        if ($delayMs === 0) {
            return;
        }

        usleep($delayMs * 1000);
    }
}
