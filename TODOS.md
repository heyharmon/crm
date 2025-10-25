# App To-Dos

1. **Convert deduplication command into a service**

    - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
    - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. **Rename and expand the dashboard**

    - Rename `resources/js/pages/Home.vue` to `Dashboard.vue` and wire the router/menu to the new name.
    - Scaffold a simple dashboard layout showing total organizations, rating distribution counts per option, the number of organizations without ratings, and how many websites the current user has rated.
    - Keep the UI lightweight but visually consistent with the rest of the app (cards, typography, spacing).

3. In the OrganizationGridView.vue, OrganizationForm.vue and OrganizationDetails components, remove the formatDate function and use moment instead. Then search the codebase frontend for other instances of manual data formatting. Search for formatDate, DateTimeFormat and Intl in the codebase frontend to attempt finding other instances.

4. Clean up some database table naming

5. Rename the config/redesign.php config file to waybackmachine.php because it relates to the wayback machine and so this name is more appropriate. When you rename the file, make sure references to the variables elsewhere in the code are fixed and remain functional after this rename.
