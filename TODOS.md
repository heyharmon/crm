# App To-Dos

1. **Convert deduplication command into a service**
   - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
   - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. **Color-code website rating UI**
   - Style the rating buttons on `resources/js/pages/websites/WebsiteRatings.vue` so the labels map to colors (excellent → green, good → light green, okay → yellow, poor → orange, really bad → red) for instant visual feedback.
   - Apply the same color scheme to the website rating summary badge in `OrganizationTableView.vue` based on each row’s `website_rating_summary`.
   - Mirror that treatment in the Organization Details component so its `website_rating_summary` reflects the matching color.

3. **Rename and expand the dashboard**
   - Rename `resources/js/pages/Home.vue` to `Dashboard.vue` and wire the router/menu to the new name.
   - Scaffold a simple dashboard layout showing total organizations, rating distribution counts per option, the number of organizations without ratings, and how many websites the current user has rated.
   - Keep the UI lightweight but visually consistent with the rest of the app (cards, typography, spacing).

4. **Retire unused OrganizationShow page**
   - Audit `resources/js/pages/organizations/OrganizationShow.vue` usage (routes, links, lazy imports). If it’s truly unused, remove the route and file.
   - Confirm the Organization Index + `OrganizationDetails.vue` combo covers all navigation paths before deleting.

5. **Condense table row actions**
   - Replace the Edit/Scrape/Delete buttons in `resources/js/components/organizations/OrganizationTableView.vue` with a single ellipsis trigger.
   - Clicking the ellipsis should open a menu that contains those three actions so the table stays compact.

6. **Show dash when page count missing**
   - In the `pages` column of `OrganizationTableView.vue`, render `—` instead of `0` whenever the page count is `0`, `null`, or otherwise missing, signaling the org hasn’t been scraped yet.

7. **Display filtered totals**
   - On `resources/js/pages/organizations/OrganizationIndex.vue`, append the count of organizations that match the current filters next to the “Organizations” heading (keeping the existing grid/table toggle and action buttons).
