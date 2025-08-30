# Website Scraping (Apify Crawler)

## Overview
Scrapes an organization's website using an Apify crawler actor and stores discovered pages in the `pages` table.

## Flow
- **Trigger**: `resources/js/pages/organizations/OrganizationIndex.vue` calls `POST /api/web-scraper/start` from the listing actions.
- **Controller**: `app/Http/Controllers/WebScraperController.php` validates input and dispatches jobs.
- **Jobs**:
  - `app/Jobs/StartWebScrapingJob.php`: starts the Apify run via `ApifyCrawlerService`.
  - `app/Jobs/MonitorWebScrapingJob.php`: polls run status until completion.
  - `app/Jobs/ProcessWebScrapingResultsJob.php`: fetches dataset and persists `Page` records.
- **Service**: `app/Services/ApifyCrawlerService.php` (actor run, status, dataset, and result processing).
- **Model**: `app/Models/ApifyRun.php` stores run metadata.

## API
- `POST /api/web-scraper/start`
  - Body: `organization_id` (required), `max_pages` (optional), `max_depth` (optional)
  - Behavior: starts the actor and enqueues monitoring.
- `GET /api/web-scraper/runs`
  - Lists runs for the current user (filtered by actor, see notes below).
- `GET /api/web-scraper/runs/{apifyRun}`
  - Returns a single run if owned by the current user.

## Actor and Input
- Actor ID (constant in service): currently `heyharmon~cheerio-crawler-task`.
- Input built from organization website:
  - `startUrls`: the organization website URL
  - `pseudoUrls`: origin + `/*` style pattern
  - Limits: `maxPagesPerCrawl`, `maxCrawlingDepth`
  - `linkSelector`: `a[href]`
  - `proxyConfiguration.useApifyProxy`: true

You can swap to a Puppeteer actor by changing `ApifyCrawlerService::ACTOR_ID`.

## Result Processing
`ProcessWebScrapingResultsJob` calls `ApifyCrawlerService::processResults()` which:
- Reads the organization id from the run input data
- Upserts `Page` records by `(organization_id, url)` and updates `title`
- Returns a summary of created/updated pages

## Configuration
- Env: set `services.apify.token` in `config/services.php` to a valid token.
- Actor ID: adjust in `ApifyCrawlerService::ACTOR_ID` if needed.

## Notes & Caveats
- The controller `getScrapingRuns` filters by `actor_id`. Ensure `ApifyRun` persists `actor_id` and matches the service actor ID if you rely on filtering.
- If you switch actors (e.g., Puppeteer vs Cheerio), update any filtering and docs to match.
