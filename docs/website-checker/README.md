# Website Status Checker

`CheckOrganizationWebsiteStatus` is a queued job that normalizes an organization’s website URL, probes the host with a lightweight HTTP request strategy, and stores the resulting status on the `organizations` table. It ensures the UI can surface whether a company’s site is reachable, redirected, or unavailable without blocking user actions.

## When It Runs

-   `OrganizationObserver@created` dispatches the job each time a new organization record is persisted.
-   HubSpot imports, Google Maps scraper ingests, and any manual creations all trigger the observer, so every record eventually receives a status.
-   The job is fire-and-forget with `tries = 1` and `timeout = 30`, keeping the queue free of retries when connectivity issues arise.

## Request Flow

1. Normalize the stored website using `App\Support\WebsiteUrl::normalize`. Missing or invalid URLs immediately mark the organization as `down`.
2. Attempt up to three `HEAD` requests without auto-following redirects. Responses are classified as:
    - `up` when any attempt returns a successful response (2xx).
    - `redirected` when success follows a cross-domain redirect.
3. If the `HEAD` loop never succeeds, make a final `GET` request that allows redirects. Success still maps to `up` or `redirected` depending on whether the host changed.
4. Capture network exceptions (timeouts, SSL failures, etc.) and translate them into `down` unless a later request succeeds.

All HTTP calls share a 5 second per-request timeout, send a dedicated user-agent header, and log failures at debug level for later inspection.

## Status Codes Persisted

The job writes one of the following constants on the `Organization` model:

| Status       | Meaning                                                                              |
| ------------ | ------------------------------------------------------------------------------------ |
| `up`         | Reached successfully without cross-domain redirects.                                 |
| `redirected` | Completed after at least one redirect that changed the host.                         |
| `down`       | Failed due to missing URL, network or SSL errors, or repeated non-success responses. |
| `unknown`    | Reserved for future callers; this job never writes it today.                         |

Because writes use `updateQuietly`, no model events fire during the status update, preventing recursive queue dispatches.
