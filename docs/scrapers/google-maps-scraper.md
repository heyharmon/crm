# Google Maps Scraper

## Overview
Imports organizations into the CRM by running an Apify Google Maps actor and mapping the dataset into `organizations`.

## Flow
- Page: `resources/js/pages/organizations/OrganizationImport.vue`
- Store: `resources/js/stores/apifyImportStore.js` (calls `/api/google-maps-scraper/*`)
- Controller: `app/Http/Controllers/OrganizationGoogleMapsScraperController.php`
- Jobs (generic): `StartApifyActorJob` → `MonitorApifyRunJob` → `ProcessApifyResultsJob`
- Services: `ApifyGoogleMapsScraperService` (actor run/status/results)
- Processor: `OrganizationGoogleMapsResultsProcessor` → `GoogleMapsOrganizationImportService`
- Model: `ApifyRun`

## API
- POST `/api/google-maps-scraper/start`
- GET `/api/google-maps-scraper/runs`
- GET `/api/google-maps-scraper/runs/{apifyRun}`

## Data Mapping
`GoogleMapsOrganizationImportService` maps dataset items to `organizations` with dedupe by `google_place_id`. Creates/updates orgs; links/creates categories.

## Config
- `services.apify.token`
- Actor: `ApifyGoogleMapsScraperService::ACTOR_ID` (`heyharmon~google-maps-extractor`)

## Notes
- Only process results for `SUCCEEDED` runs
- Progress via `ApifyRun::progress_percentage`
