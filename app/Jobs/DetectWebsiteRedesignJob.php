<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\WebsiteRedesignDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetectWebsiteRedesignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $organizationId)
    {
    }

    public function handle(WebsiteRedesignDetector $detector): void
    {
        $organization = Organization::find($this->organizationId);

        if (!$organization) {
            Log::warning('Website redesign job skipped missing organization', [
                'organization_id' => $this->organizationId,
            ]);
            return;
        }

        if (!$organization->website) {
            Log::info('Website redesign job skipped organization without website', [
                'organization_id' => $organization->id,
            ]);
            return;
        }

        if ($organization->last_major_redesign_at) {
            DB::transaction(function () use ($organization) {
                $organization->websiteRedesigns()->delete();
                $organization->last_major_redesign_at = null;
                $organization->save();
            });
        }

        $events = $detector->detect($organization->website);

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
}
