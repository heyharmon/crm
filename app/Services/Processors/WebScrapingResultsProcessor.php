<?php

namespace App\Services\Processors;

use App\Models\ApifyRun;
use App\Services\ApifyWebCrawlerService;
use App\Services\Processors\Contracts\ApifyResultsProcessor;

class WebScrapingResultsProcessor implements ApifyResultsProcessor
{
    public function __construct(private ApifyWebCrawlerService $crawlerService)
    {
    }

    public function process(ApifyRun $apifyRun, array $items): array
    {
        return $this->crawlerService->processResults($apifyRun, $items);
    }
}
