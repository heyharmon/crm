# App To-Dos

1. **Convert deduplication command into a service**

    - Instead of running `DeduplicateOrganizationsByWebsiteCommand` manually, expose its logic through a service (or similar) so it can run automatically during the Apify → Google Maps import flow.
    - Hook the service into the organization import path that handles Google Maps scraper results, ensuring organizations brought in from Apify are deduplicated as part of that process.

2. Clean up some database table naming

3. On the WebsiteRatings.vue I can rate organization websites. When I rate a website another organization website that I have not yet rated is loaded. The websites are loaded in default id order from the database. Instead of default id order, I want them to be random–not by id, or name/title or anything else–I want the website that I am rating to be a random website I have not yet loaded. The reason for this is that I when users are rating websites I want the best chance for all websites to have at least 1 rating. If the organization website is loaded at random for the user, then we can get more coverage.

4. Add a delay to the DetectWebsiteRedesignJob.php job. I want to add a delay so that when this job is run for many websites at a time we do not overwhelm the Wayback Machine server and hit a rate limit.

5. Import NCUA data about credit union assets into this app.

6. Some jobs use a service and others don't. I want each job to have a service it uses. This way I can work on the services, not the jobs. These jobs would be the CrawlSitemapJob and DetectWebsiteRedesignJob

7. Out the Apify jobs into an Apify folder in the Jobs folder.

8. Some organization websites cannot be reached because the website is down, or no longer in operation. For example yourcommunitybank.com’s server IP address could not be found. I need a job that checks if the an organizations website is up or down. A super simple checker. And I need it to run anytime an organization is created including when an organization is imported via CSV via the hubspot org import service and imported from apify via the apify google maps scraper service. Add a website_status column after the 'website' column on the org model that can be used to store the status of the website.
