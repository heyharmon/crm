# Scrapers Domain

## Purpose
Interfaces with external scraping services to import organization data and crawl websites.

## Backend
- **Controllers**: `GoogleMapsScraperController` starts Google Maps imports and lists their status; `WebScraperController` starts website crawls and reports run details.
- **Jobs**: `StartApifyScrapingJob`, `MonitorApifyRunJob`, `ProcessApifyResultsJob`, `StartWebScrapingJob`, `MonitorWebScrapingJob`, and `ProcessWebScrapingResultsJob` orchestrate asynchronous scraping workflows.
- **Services**: `BaseApifyService` wraps Apify API calls for Google Maps imports; `BaseFirecrawlService` and `FirecrawlMapService` integrate with Firecrawl's Map API for website crawling; `OrganizationImportService` maps scraped results into organization records.
- **Model**: `ApifyRun` stores metadata and progress for each scraping run.

## Frontend
- **Page**: `resources/js/pages/organizations/Import.vue` lets users start Google Maps imports and view run history.
- **Store**: `resources/js/stores/apifyImportStore.js` manages import requests and pagination of past runs.
