# App To-Dos

1. **Convert deduplication command into a service**
   - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
   - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. **Rename and expand the dashboard**
   - Rename `resources/js/pages/Home.vue` to `Dashboard.vue` and wire the router/menu to the new name.
   - Scaffold a simple dashboard layout showing total organizations, rating distribution counts per option, the number of organizations without ratings, and how many websites the current user has rated.
   - Keep the UI lightweight but visually consistent with the rest of the app (cards, typography, spacing).

3. **Condense table row actions**
   - Replace the Edit/Scrape/Delete buttons in `resources/js/components/organizations/OrganizationTableView.vue` with a single ellipsis trigger.
   - Clicking the ellipsis should open a menu that contains those three actions so the table stays compact.

4. **Display filtered totals**
   - On `resources/js/pages/organizations/OrganizationIndex.vue`, append the count of organizations that match the current filters next to the “Organizations” heading (keeping the existing grid/table toggle and action buttons).
