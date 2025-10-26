<?php

namespace App\Services\Processors;

use App\Models\ApifyRun;
use App\Services\GoogleMapsOrganizationImportService;
use App\Services\Processors\Contracts\ApifyResultsProcessor;

class OrganizationGoogleMapsResultsProcessor implements ApifyResultsProcessor
{
    public function __construct(private GoogleMapsOrganizationImportService $importService)
    {
    }

    public function process(ApifyRun $apifyRun, array $items): array
    {
        return $this->importService->importFromApifyData($items, $apifyRun);
    }
}
