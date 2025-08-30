# Scrapers

Overview docs for the scraping domain. See dedicated pages for each workflow:

- Google Maps scraping and import: `docs/scrapers/google-maps-scraper.md`
- Website scraping (crawler): `docs/scrapers/web-scraper.md`

Key building blocks shared by both:
- Jobs: `StartApifyActorJob`, `MonitorApifyRunJob`, `ProcessApifyResultsJob`
- Base service for Apify: `app/Services/BaseApifyService.php`
- Run metadata: `app/Models/ApifyRun.php`
