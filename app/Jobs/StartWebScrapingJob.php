<?php

namespace App\Jobs;

use App\Services\FirecrawlMapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartWebScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private array $params, private int $userId) {}

    public function handle(FirecrawlMapService $scraperService): void
    {
        try {
            $apifyRun = $scraperService->startScraping($this->params, $this->userId);
            MonitorWebScrapingJob::dispatch($apifyRun->id)->delay(5);
        } catch (\Exception $e) {
            Log::error('Failed to start web scraping job', [
                'params' => $this->params,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
