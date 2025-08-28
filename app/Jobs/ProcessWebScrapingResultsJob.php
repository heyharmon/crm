<?php

namespace App\Jobs;

use App\Models\ApifyRun;
use App\Services\PuppeteerCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebScrapingResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $apifyRunId) {}

    public function handle(PuppeteerCrawlerService $scraperService): void
    {
        $apifyRun = ApifyRun::findOrFail($this->apifyRunId);
        try {
            $data = $scraperService->getRunResults($apifyRun);
            if (empty($data)) {
                Log::warning('No data found in web scraping run results', [
                    'run_id' => $apifyRun->apify_run_id,
                ]);
                return;
            }
            $results = $scraperService->processResults($apifyRun, $data);
            Log::info('Successfully processed web scraping results', [
                'run_id' => $apifyRun->apify_run_id,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process web scraping results', [
                'apify_run_id' => $apifyRun->id,
                'error' => $e->getMessage(),
            ]);
            $apifyRun->update([
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
