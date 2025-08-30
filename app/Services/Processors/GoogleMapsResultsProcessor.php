<?php

namespace App\Services\Processors;

use App\Models\ApifyRun;
use App\Services\OrganizationImportService;
use App\Services\Processors\Contracts\ApifyResultsProcessor;

class GoogleMapsResultsProcessor implements ApifyResultsProcessor
{
    public function __construct(private OrganizationImportService $importService)
    {
    }

    public function process(ApifyRun $apifyRun, array $items): array
    {
        return $this->importService->importFromApifyData($items, $apifyRun);
    }
}

