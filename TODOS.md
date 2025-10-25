# App To-Dos

1. **Convert deduplication command into a service**
   - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
   - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. **Use website screenshots in organization details**
   - Swap the Google Maps image in the organization details component for the website screenshot.
   - Reuse the simplest approach from `resources/js/pages/organization-websites/OrganizationWebsiteRatings.vue` for fetching/rendering the screenshot so the detail view shows the website preview at the top.

3. **Color-code website rating UI**
   - Style the rating buttons on `resources/js/pages/organization-websites/OrganizationWebsiteRatings.vue` so the labels map to colors (excellent → green, good → light green, okay → yellow, poor → orange, really bad → red) for instant visual feedback.
   - Apply the same color scheme to the website rating summary badge in `OrganizationTableView.vue` based on each row’s `website_rating_summary`.
   - Mirror that treatment in the Organization Details component so its `website_rating_summary` reflects the matching color.

4. **Rename and expand the dashboard**
   - Rename `resources/js/pages/Home.vue` to `Dashboard.vue` and wire the router/menu to the new name.
   - Scaffold a simple dashboard layout showing total organizations, rating distribution counts per option, the number of organizations without ratings, and how many websites the current user has rated.
   - Keep the UI lightweight but visually consistent with the rest of the app (cards, typography, spacing).

5. **Retire unused OrganizationShow page**
   - Audit `resources/js/pages/organizations/OrganizationShow.vue` usage (routes, links, lazy imports). If it’s truly unused, remove the route and file.
   - Confirm the Organization Index + `OrganizationDetails.vue` combo covers all navigation paths before deleting.
