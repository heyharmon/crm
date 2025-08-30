<?php

namespace App\Jobs;

use App\Services\ApifyGoogleMapsScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartGoogleMapsScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private array $params, private int $userId) {}

    public function handle(ApifyGoogleMapsScraperService $scraperService): void
    {
        try {
            $apifyRun = $scraperService->startScraping($this->params, $this->userId);
            MonitorGoogleMapsScrapingJob::dispatch($apifyRun->id)->delay(5);
        } catch (\Exception $e) {
            Log::error('Failed to start Google Maps scraping job', [
                'params' => $this->params,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
