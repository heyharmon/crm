<?php

namespace App\Jobs;

use App\Models\ApifyRun;
use App\Services\ApifyGoogleMapsScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorGoogleMapsScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 180;
    public int $backoff = 10;

    public function __construct(private int $apifyRunId) {}

    public function handle(ApifyGoogleMapsScraperService $scraperService): void
    {
        $apifyRun = ApifyRun::findOrFail($this->apifyRunId);
        try {
            $apifyRun = $scraperService->updateRunStatus($apifyRun);
            if ($apifyRun->isCompleted()) {
                if ($apifyRun->isSuccessful()) {
                    ProcessGoogleMapsResultsJob::dispatch($apifyRun->id);
                } else {
                    Log::error('Apify run failed', [
                        'run_id' => $apifyRun->apify_run_id,
                        'status' => $apifyRun->status,
                    ]);
                }
            } else {
                MonitorGoogleMapsScrapingJob::dispatch($apifyRun->id)->delay(30);
            }
        } catch (\Exception $e) {
            Log::error('Failed to monitor Apify run', [
                'apify_run_id' => $apifyRun->id,
                'error' => $e->getMessage(),
            ]);
            $apifyRun->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
