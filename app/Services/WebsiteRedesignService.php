<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebsiteRedesignService
{
    public function __construct(private WebsiteRedesignDetector $detector)
    {
    }

    public function refreshOrganizationRedesigns(Organization $organization): void
    {
        $this->throttleWaybackRequests();

        if ($organization->last_major_redesign_at) {
            DB::transaction(function () use ($organization) {
                $organization->websiteRedesigns()->delete();
                $organization->last_major_redesign_at = null;
                $organization->save();
            });
        }

        $events = $this->detector->detect($organization->website);

        DB::transaction(function () use ($organization, $events) {
            $organization->websiteRedesigns()->delete();

            foreach ($events as $event) {
                $organization->websiteRedesigns()->create([
                    'digest' => $event['digest'],
                    'wayback_timestamp' => $event['timestamp'],
                    'captured_at' => $event['captured_at'],
                    'persistence_days' => $event['persistence_days'],
                    'is_major' => true,
                ]);
            }

            $lastEvent = empty($events) ? null : $events[count($events) - 1];
            $organization->last_major_redesign_at = $lastEvent['captured_at'] ?? null;
            $organization->save();
        });

        Log::info('Website redesign detection finished', [
            'organization_id' => $organization->id,
            'events_recorded' => count($events),
            'last_major_redesign_at' => optional($organization->last_major_redesign_at)->toDateString(),
        ]);
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
