# Importing

Documentation for the organization import flows that live under **Organizations → Import** in the CRM. Each pipeline has a dedicated page that covers workflow, matching logic, and data mapping.

-   Google Maps scraping and import: `docs/importing/google-maps-import.md`
-   HubSpot CSV import from company exports: `docs/importing/hubspot-import.md`
-   NCUA quarterly data refresh: `docs/importing/ncua-import.md`

The HubSpot and NCUA importers share the same upload UX and response reporting. The HubSpot flow can optionally seed new organizations when no match exists, while the NCUA flow is strictly update-only so financial metrics remain aligned with the authoritative regulator data.

Google Maps imports rely on our Apify infrastructure:

-   Jobs: `StartApifyActorJob`, `MonitorApifyRunJob`, `ProcessApifyResultsJob`
-   Base service for Apify: `app/Services/BaseApifyService.php`
-   Run metadata: `app/Models/ApifyRun.php`
