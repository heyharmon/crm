<?php

namespace App\Services;

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
                    'digest' => $event['digest'],
                    'wayback_timestamp' => $event['timestamp'],
                    'captured_at' => $event['captured_at'],
                    'persistence_days' => $event['persistence_days'],
                    'is_major' => true,
                ]);
            }

            $lastEvent = empty($result->events) ? null : $result->events[count($result->events) - 1];
            $organization->last_major_redesign_at = $lastEvent['captured_at'] ?? null;
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
