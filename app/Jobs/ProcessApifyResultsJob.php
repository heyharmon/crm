<?php

namespace App\Jobs;

use App\Models\ApifyRun;
use App\Services\BaseApifyService;
use App\Services\Processors\Contracts\ApifyResultsProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessApifyResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $apifyRunId,
        private string $serviceClass,
        private string $processorClass
    ) {}

    public function handle(): void
    {
        $apifyRun = ApifyRun::findOrFail($this->apifyRunId);

        /** @var BaseApifyService $service */
        $service = app()->make($this->serviceClass);
        /** @var ApifyResultsProcessor $processor */
        $processor = app()->make($this->processorClass);

        try {
            $data = $service->getRunResults($apifyRun);
            if (empty($data)) {
                Log::warning('No data found in Apify run results', [
                    'run_id' => $apifyRun->apify_run_id,
                ]);
                return;
            }
            $results = $processor->process($apifyRun, $data);
            Log::info('Successfully processed Apify results', [
                'run_id' => $apifyRun->apify_run_id,
                'service' => $this->serviceClass,
                'processor' => $this->processorClass,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process Apify results', [
                'apify_run_id' => $apifyRun->id,
                'service' => $this->serviceClass,
                'processor' => $this->processorClass,
                'error' => $e->getMessage(),
            ]);
            $apifyRun->update([
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}

