# App To-Dos

1. **Convert deduplication command into a service**
   - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
   - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. **Use website screenshots in organization details**
   - Swap the Google Maps image in the organization details component for the website screenshot.
   - Reuse the simplest approach from `WebsiteRatings.vue` for fetching/rendering the screenshot so the detail view shows the website preview at the top.

3. **Color-code website rating UI**
   - Style the rating buttons on `WebsiteRatings.vue` so the labels map to colors (excellent → green, good → light green, okay → yellow, poor → orange, really bad → red) for instant visual feedback.
   - Apply the same color scheme to the website rating summary badge in `OrganizationTableView.vue` based on each row’s `website_rating_summary`.
   - Mirror that treatment in the Organization Details component so its `website_rating_summary` reflects the matching color.
