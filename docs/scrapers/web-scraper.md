# Website Scraping (Sitemap Crawler)

## Overview
Scrapes an organization's website by traversing its `sitemap.xml` (and nested sitemap index files) and persists the discovered pages in the `pages` table.

## Flow
- **Trigger**: `resources/js/pages/organizations/OrganizationIndex.vue` calls `POST /api/web-scraper/start` from the listing actions.
- **Controller**: `app/Http/Controllers/WebsitePageScraperController.php` validates input and dispatches the crawler job.
- **Job**: `app/Jobs/CrawlSitemapJob.php` resolves the target organization's sitemap structure via `SitemapCrawlerService` and upserts `Page` records.
- **Service**: `app/Services/SitemapCrawlerService.php` fetches `robots.txt`, sitemap indexes, and URL sets while guarding against runaway traversals.

## API
- `POST /api/web-scraper/start`
  - Body: `organization_id` (required)
  - Behavior: queues the sitemap crawl job; progress is tracked via queue logs/notifications rather than Apify runs.

## Notes & Caveats
- The crawler normalizes incoming website URLs by forcing HTTPS if no protocol is provided.
- Sitemap traversal currently soft-limits to 30 sitemap fetches and 5,000 URLs per organization to avoid exhausting resources.
- `robots.txt`-provided sitemap URLs that are relative paths are resolved against the organization base URL.
