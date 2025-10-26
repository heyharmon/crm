# App To-Dos

1. **Convert deduplication command into a service**

    - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
    - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. Clean up some database table naming

3. Import NCUA data about credit union assets into this app.
