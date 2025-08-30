<?php

namespace App\Services\Processors\Contracts;

use App\Models\ApifyRun;

interface ApifyResultsProcessor
{
    /**
     * Process dataset items for a given Apify run.
     * Should return a concise summary array for logging/metrics.
     */
    public function process(ApifyRun $apifyRun, array $items): array;
}

