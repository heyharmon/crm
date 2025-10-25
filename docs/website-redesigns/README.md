# Website Redesign Detection

Tracks major homepage redesigns per organization by querying the Internet Archive CDX API and surfacing the resulting history in the CRM UI.

## Data Model
- **organization_website_redesigns**: stores one row per major digest change (Wayback timestamp, digest hash, and persistence window in days). Rows are refreshed each time the detection job runs.
- **organizations.last_major_redesign_at**: cached date of the most recent major redesign to support lightweight list views and filters.

## Backend Flow
1. **DetectWebsiteRedesignJob** (`app/Jobs/DetectWebsiteRedesignJob.php`)
   - Accepts an `organization_id` and skips work if the record or website URL is missing.
   - Calls `WebsiteRedesignDetector` to fetch CDX snapshots and identify digest windows that remain stable for the configured duration (`config/redesign.php`).
   - Replaces existing redesign rows for the organization, then updates `last_major_redesign_at` using the newest detected event.
2. **WebsiteRedesignDetector** (`app/Services/WebsiteRedesignDetector.php`)
   - Normalizes the organization’s hostname, fetches collapsed digest rows via the CDX API, and parses timestamps.
   - Flags “major” redesigns when the digest remains unchanged for the configured minimum number of days (default 120). Only the most recent N events (default 5) are kept.
3. **OrganizationController@show** eagerly loads the redesign rows (newest first) so the frontend can render the timeline alongside the organization profile.

## Frontend Updates
- **OrganizationDetail panel** now shows the last redesign date plus a list of detected events, including how long each digest version stayed stable.
- **Grid/Table views** both show the cached “Last redesign” date when present, giving users quick insight while browsing.

## Operations
- Tune detection thresholds via `config/redesign.php` (endpoint, minimum persistence days, and event cap).
- Schedule or manually dispatch `DetectWebsiteRedesignJob` whenever organization websites change or on a periodic cadence to refresh redesign insights.
