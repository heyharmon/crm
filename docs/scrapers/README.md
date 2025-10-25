# Scrapers

Overview docs for the scraping domain. See dedicated pages for each workflow:

- Google Maps scraping and import: `docs/scrapers/google-maps-scraper.md`
- Website scraping (crawler): `docs/scrapers/web-scraper.md`

Google Maps imports rely on our Apify infrastructure:
- Jobs: `StartApifyActorJob`, `MonitorApifyRunJob`, `ProcessApifyResultsJob`
- Base service for Apify: `app/Services/BaseApifyService.php`
- Run metadata: `app/Models/ApifyRun.php`
