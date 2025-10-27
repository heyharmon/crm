<?php

namespace App\Observers;

use App\Jobs\CheckOrganizationWebsiteStatus;
use App\Jobs\PreloadOrganizationScreenshot;
use App\Models\Organization;

class OrganizationObserver
{
    public function created(Organization $organization): void
    {
        CheckOrganizationWebsiteStatus::dispatch($organization->id);
        $this->dispatchScreenshotPreload($organization);
    }

    public function updated(Organization $organization): void
    {
        if ($organization->wasChanged('website')) {
            $this->dispatchScreenshotPreload($organization);
        }
    }

    private function dispatchScreenshotPreload(Organization $organization): void
    {
        $website = trim((string) $organization->website);
        if ($website === '') {
            return;
        }

        PreloadOrganizationScreenshot::dispatch($organization->id);
    }
}
