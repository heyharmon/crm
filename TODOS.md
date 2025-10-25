# App To-Dos

1. **Convert deduplication command into a service**

    - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
    - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. **Rename and expand the dashboard**

    - Rename `resources/js/pages/Home.vue` to `Dashboard.vue` and wire the router/menu to the new name.
    - Scaffold a simple dashboard layout showing total organizations, rating distribution counts per option, the number of organizations without ratings, and how many websites the current user has rated.
    - Keep the UI lightweight but visually consistent with the rest of the app (cards, typography, spacing).

3. **Detect last major website redesign via Wayback Machine**
    - Build a service that calls the Internet Archive CDX API (e.g., `https://web.archive.org/cdx/search/cdx?url=:domain&output=json&fl=timestamp,digest&collapse=digest`) to retrieve the digest timeline for an organization’s homepage.
    - Analyze the digest sequence to find the most recent “major change” defined as a digest shift that then remains stable across subsequent captures for at least several months (configurable window).
    - Store the derived “last major redesign” date on the organization, expose it via API responses, and surface it in the UI where appropriate.
    - Include fallbacks for sites with sparse archives (e.g., return `null` when no stable change is detected) and unit tests covering digest parsing and change detection edge cases.
