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

6. Import NCUA data about credit union assets into this app.

7. Add the ability to multi select organizations from the organizations index page. I can select individual orgs by row, each row shows a checkbox. I can also hold the shift key and select a group of orgs in the table by selecting one holding shift then selecting another in the table and have all orgs between the two be selected. I can also check a select all checkbox that selects all the orgs on the screen (given the query). When I have one or more orgs selected more options appear for running actions against the group of selected orgs, namely the option to "Count pages" and "Detect redesign" for all the selected orgs. Keep this new feature as clean and simple as possible. Abstract code into a composable if necessary in order to keep the pages and other components simple. Follow patterns seen in other pages and components to maintain consistency of the frontend and backend. Create a dedicated controller or batch organization operations.
