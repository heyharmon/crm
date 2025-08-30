<?php

namespace App\Jobs;

use App\Models\ApifyRun;
use App\Services\FirecrawlMapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorWebScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 180;
    public int $backoff = 10;

    public function __construct(private int $apifyRunId) {}

    public function handle(FirecrawlMapService $scraperService): void
    {
        $apifyRun = ApifyRun::findOrFail($this->apifyRunId);
        try {
            $apifyRun = $scraperService->updateRunStatus($apifyRun);
            if ($apifyRun->isCompleted()) {
                if ($apifyRun->isSuccessful()) {
                    ProcessWebScrapingResultsJob::dispatch($apifyRun->id);
                } else {
                    Log::error('Web scraping run failed', [
                        'run_id' => $apifyRun->apify_run_id,
                        'status' => $apifyRun->status,
                    ]);
                }
            } else {
                MonitorWebScrapingJob::dispatch($apifyRun->id)->delay(30);
            }
        } catch (\Exception $e) {
            Log::error('Failed to monitor web scraping run', [
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
