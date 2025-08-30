# Google Maps Organization Import

## Overview
Imports organizations into the CRM by running an Apify Google Maps actor and mapping the dataset into `organizations`.

## Flow
- **Page**: `resources/js/pages/organizations/OrganizationImport.vue` submits import params and lists run history.
- **Store**: `resources/js/stores/apifyImportStore.js` calls backend `/google-maps-scraper` endpoints and manages state.
- **Controller**: `app/Http/Controllers/GoogleMapsScraperController.php` validates input and dispatches jobs.
- **Jobs**:
  - `app/Jobs/StartGoogleMapsScrapingJob.php`: starts the Apify run via `ApifyGoogleMapsScraperService`.
  - `app/Jobs/MonitorGoogleMapsScrapingJob.php`: polls run status until completion.
  - `app/Jobs/ProcessGoogleMapsResultsJob.php`: fetches dataset via `ApifyGoogleMapsScraperService` and imports via `OrganizationImportService`.
- **Services**:
  - `app/Services/ApifyGoogleMapsScraperService.php`: wraps Apify actor run, status, and dataset calls.
  - `app/Services/OrganizationImportService.php`: maps dataset items to `Organization` records.
- **Model**: `app/Models/ApifyRun.php` stores run metadata, status, and item counters.

## API
- `POST /api/google-maps-scraper/start`
  - Body: `search_term` (string, required), `location` (string, required), `max_places` (int, optional), `min_rating` (float, optional), `skip_closed` (bool, optional)
  - Behavior: dispatches `StartGoogleMapsScrapingJob` and returns a toast-style message.
- `GET /api/google-maps-scraper/runs`
  - Returns paginated `ApifyRun` records for the current user.
- `GET /api/google-maps-scraper/runs/{apifyRun}`
  - Returns a single run if owned by the current user.

## Data Mapping
`OrganizationImportService` transforms Apify dataset items (from the Google Maps actor) to `organizations`:
- Name: `title`
- Google Place ID: `query_place_id` extracted from `url` (fallback: md5(url))
- Banner: `imageUrl`
- Score: `totalScore`
- Reviews: `reviewsCount`
- Address: `street`, `city`, `state`, `countryCode`
- Website: normalized origin from `website`
- Phone: `phone`
- Category: creates/links `OrganizationCategory` by `categoryName`
- Map URL: `url`

Duplicates are handled by `google_place_id`:
- Existing active org → update fields
- Existing soft-deleted org → skip
- Not found → create new

Run counters on `ApifyRun` are updated: `items_processed`, `items_imported`, `items_updated`, `items_skipped`.

## Configuration
- Env: set `services.apify.token` in `config/services.php` to a valid Apify API token.
- Actor: `ApifyGoogleMapsScraperService::ACTOR_ID` = `heyharmon~google-maps-extractor`.

## Operational Notes
- Monitoring: `MonitorGoogleMapsScrapingJob` retries with backoff until a terminal status (`SUCCEEDED`, `FAILED`, `ABORTED`).
- Results fetch: only attempted for `SUCCEEDED` runs.
- Frontend polling: the UI refresh button triggers `/google-maps-scraper/runs`; progress is estimated via `ApifyRun::progress_percentage`.

## Related
- Website/Pages scraping via Apify crawler: see `docs/scrapers/web-scraper.md`.
