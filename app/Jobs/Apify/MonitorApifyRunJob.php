<?php

namespace App\Jobs\Apify;

use App\Models\ApifyRun;
use App\Services\BaseApifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorApifyRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 180;
    public int $backoff = 10;

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
        try {
            $apifyRun = $service->updateRunStatus($apifyRun);
            if ($apifyRun->isCompleted()) {
                if ($apifyRun->isSuccessful()) {
                    ProcessApifyResultsJob::dispatch($apifyRun->id, $this->serviceClass, $this->processorClass);
                } else {
                    Log::error('Apify run failed', [
                        'run_id' => $apifyRun->apify_run_id,
                        'status' => $apifyRun->status,
                    ]);
                }
            } else {
                MonitorApifyRunJob::dispatch($apifyRun->id, $this->serviceClass, $this->processorClass)->delay(30);
            }
        } catch (\Exception $e) {
            Log::error('Failed to monitor Apify run', [
                'apify_run_id' => $apifyRun->id,
                'service' => $this->serviceClass,
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
