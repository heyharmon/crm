<?php

namespace App\Services\Apify\Processors;

use App\Models\ApifyRun;
use App\Services\Apify\Processors\Contracts\ApifyResultsProcessor;
use App\Services\Imports\GoogleMapsOrganizationImportService;

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
