# Website Crawler

## Overview

Scrapes an organization's website using two methods:
1. **Sitemap Crawler**: Traverses `sitemap.xml` files (and nested sitemap index files) to discover pages
2. **Deep Crawler**: When no sitemap is found, automatically falls back to manually crawling the website by following internal links

Both methods persist discovered pages in the `pages` table and update the organization's `website_crawl_status` and `website_crawl_message` fields.

## Flow

### Sitemap Crawler

-   **Trigger**: `resources/js/pages/organizations/OrganizationIndex.vue` calls `POST /api/web-scraper/start` from the listing actions.
-   **Controller**: `app/Http/Controllers/WebsitePageScraperController.php` validates input and dispatches the crawler job.
-   **Job**: `app/Jobs/CrawlSitemapJob.php` resolves the target organization's sitemap structure via `SitemapCrawlerService` and upserts `Page` records.
-   **Service**: `app/Services/Sitemap/SitemapCrawlerService.php` fetches `robots.txt`, sitemap indexes, and URL sets while guarding against runaway traversals.

### Deep Crawler

-   **Automatic Trigger**: When `OrganizationSitemapSyncService` detects no sitemap is found, it automatically dispatches `CrawlWebsiteDeep` job.
-   **Job**: `app/Jobs/CrawlWebsiteDeep.php` uses `DeepWebsiteCrawlerService` to recursively crawl the website by following internal links.
-   **Service**: `app/Services/Sitemap/DeepWebsiteCrawlerService.php`:
    - Starts from the organization's website URL
    - Fetches HTML content and extracts internal links using regex
    - Normalizes URLs and matches domains (e.g., `domain.com` matches `www.domain.com`, `https://domain.com`, `http://www.domain.com`)
    - Recursively crawls discovered internal links up to a maximum of 100 URLs
    - Uses `OrganizationSitemapSyncService::syncUrls()` to persist discovered pages

## API

-   `POST /api/web-scraper/start`
    -   Body: `organization_id` (required)
    -   Behavior: queues the sitemap crawl job; if no sitemap is found, automatically queues the deep crawl job. Progress is tracked via `website_crawl_status` and `website_crawl_message` columns on the organization.

## Status Tracking

Both crawlers update the organization's `website_crawl_status` and `website_crawl_message` fields:

-   **`success`**: Crawl completed successfully with page count in message
-   **`failed`**: Crawl failed with error message
-   **`pending`**: Sitemap crawl found no sitemap and deep crawl has been initiated

## Notes & Caveats

### Sitemap Crawler

-   The crawler normalizes incoming website URLs by forcing HTTPS if no protocol is provided.
-   Sitemap traversal currently soft-limits to 30 sitemap fetches and 5,000 URLs per organization to avoid exhausting resources.
-   `robots.txt`-provided sitemap URLs that are relative paths are resolved against the organization base URL.

### Deep Crawler

-   Limited to a maximum of 100 internal URLs per crawl to prevent resource exhaustion.
-   Domain normalization ensures that variations like `www.domain.com`, `domain.com`, `https://domain.com`, and `http://www.domain.com` are all treated as the same domain.
-   Only follows internal links (same domain). External links, anchors (`#`), JavaScript links, mailto links, etc. are ignored.
-   Uses a breadth-first crawling approach (queue-based) to discover pages.
-   Request timeout is set to 10 seconds per page to prevent hanging on slow or unresponsive pages.
