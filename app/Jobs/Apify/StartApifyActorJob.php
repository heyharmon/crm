<?php

namespace App\Jobs\Apify;

use App\Services\BaseApifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartApifyActorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $params,
        private int $userId,
        private string $serviceClass,
        private string $processorClass
    ) {}

    public function handle(): void
    {
        /** @var BaseApifyService $service */
        $service = app()->make($this->serviceClass);
        try {
            $apifyRun = $service->startScraping($this->params, $this->userId);
            MonitorApifyRunJob::dispatch($apifyRun->id, $this->serviceClass, $this->processorClass)->delay(5);
        } catch (\Exception $e) {
            Log::error('Failed to start Apify actor job', [
                'service' => $this->serviceClass,
                'params' => $this->params,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
