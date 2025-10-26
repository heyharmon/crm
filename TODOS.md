# App To-Dos

1. **Convert deduplication command into a service**

    - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
    - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. **Rename and expand the dashboard**

    - Rename `resources/js/pages/Home.vue` to `Dashboard.vue` and wire the router/menu to the new name.
    - Scaffold a simple dashboard layout showing total organizations, rating distribution counts per option, the number of organizations without ratings, and how many websites the current user has rated.
    - Keep the UI lightweight but visually consistent with the rest of the app (cards, typography, spacing).

3. Clean up some database table naming

4. On the WebsiteRatings.vue I can rate organization websites. When I rate a website another organization website that I have not yet rated is loaded. The websites are loaded in default id order from the database. Instead of default id order, I want them to be random–not by id, or name/title or anything else–I want the website that I am rating to be a random website I have not yet loaded. The reason for this is that I when users are rating websites I want the best chance for all websites to have at least 1 rating. If the organization website is loaded at random for the user, then we can get more coverage.

5. Add a delay to the DetectWebsiteRedesignJob.php job. I want to add a delay so that when this job is run for many websites at a time we do not overwhelm the Wayback Machine server and hit a rate limit.

6. Import NCUA data about credit union assets into this app.
