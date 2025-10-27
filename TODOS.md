# App To-Dos

1. **Convert deduplication command into a service**

    - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
    - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. Clean up some database table naming

3. Import NCUA data about credit union assets into this app.

4. On the organizations index page we list organizations in a table or a grid. There is also a pagination component. I want to increase the per page to 100 so that 100 organizations are loaded per page by default. Then I also want to add a dropdown to the pagination component that allows me to select the per page amount and increase it or decrease it. You choose reasonable selections for this dropdown.
