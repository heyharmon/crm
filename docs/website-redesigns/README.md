# Website Redesign Detection

The redesign detector pinpoints the most recent “real” rebuild of an organization’s site by comparing navigation structure across Wayback Machine snapshots. Instead of relying on payload size or digest churn, we extract the archived `<nav>` markup, fingerprint the DOM hierarchy and CSS class usage, and store the pivots that represent true redesigns.

## How It Works

1. **Yearly sweep** – We pull one homepage snapshot per year (using the CDX API with `collapse=timestamp:4`) and build a navigation signature for each capture. A signature records the DOM tag hierarchy plus normalized CSS class tokens discovered inside the nav.
2. **Navigation diffing** – We score the similarity between consecutive yearly signatures. When the class-heavy similarity falls below the configured change threshold, we treat that span as a potential redesign window.
3. **Monthly refinement** – For every flagged window we fetch monthly snapshots between the “old” and “new” years, recompute nav signatures, and locate the first month whose nav matches the new layout. That snapshot becomes the recorded redesign event.
4. **Event persistence** – Each redesign event stores the last snapshot before the change plus the first snapshot after the change, alongside similarity metrics, class counts, the unique class list, and trimmed nav HTML so the UI can summarise the jump.

Snapshots that lack a usable navigation element (no `<nav>`, no `role="navigation"`, or empty menus) are skipped. Tiny payloads (default < 8 KB) are filtered out up front to avoid WAF challenges and placeholder pages.

## Data Model

- **organization_website_redesigns** – one row per detected navigation overhaul. Columns include:
    - `before_wayback_timestamp` / `before_captured_at`
    - `after_wayback_timestamp` / `after_captured_at`
    - `nav_similarity` (0–1 similarity score comparing before vs. after nav)
    - `before_nav_class_count` / `after_nav_class_count`
    - `before_nav_classes` / `after_nav_classes` (stored JSON arrays)
    - `before_nav_html` / `after_nav_html` (trimmed markup for reference/debugging)
- **organizations.last_major_redesign_at** – cached date of the newest redesign event.
- **organizations.website_redesign_status` / `website_redesign_status_message`** – status from the most recent job run so the UI can surface “Wayback failed” or “No snapshots” states.

## Backend Flow

1. **DetectWebsiteRedesignJob** (`app/Jobs/DetectWebsiteRedesignJob.php`)
    - Loads the organization and exits early when the record or website URL is missing.
    - Delegates to `WebsiteRedesignService`.
2. **WebsiteRedesignService** (`app/Services/WebsiteRedesign/WebsiteRedesignService.php`)
    - Throttles requests with the configured delay.
    - Clears previous redesign rows, runs `WebsiteRedesignDetector`, persists the new events, and updates the cached status + `last_major_redesign_at`.
3. **WebsiteRedesignDetector** (`app/Services/WebsiteRedesign/WebsiteRedesignDetector.php`)
    - Queries the CDX API for yearly snapshots, generates navigation signatures, and identifies large structural shifts.
    - Refines each change window using monthly snapshots to pinpoint the redesign month.
    - Returns a `WebsiteRedesignDetectionResult` containing the events plus a status message (`success`, `no_wayback_data`, `no_major_events`, or `wayback_failed`).
4. **OrganizationController@show** preloads redesign events (newest first) so the frontend can render the timeline and nav summary inline with the organization profile.

## Frontend Notes

- The organization detail panel now lists each redesign with its capture date, how dramatically the navigation changed, a glimpse of the CSS class shifts, plus paired “before” and “after” screenshots (linking directly to the Wayback snapshots). When the redesign job fails or produces no results, the status banner continues to explain why.

## Operations & Tuning

- Adjust the detector via `config/waybackmachine.php`:
    - `nav_similarity_change_threshold` – lower it to treat subtler class/structure adjustments as redesigns; raise it to focus on dramatic shifts.
    - `nav_similarity_match_threshold` – tighten it if noisy snapshots masquerade as the new design.
    - `min_snapshot_length_bytes` – drop when legitimate navigation lives in very small payloads.
    - `max_snapshot_results` – increase for long-lived domains so the yearly/monthly sweeps have enough data.
    - `max_events` – controls how many redesign rows we retain per organization (newest events win).
    - `request_delay_ms` / `request_timeout_seconds` – tune when Wayback rate limits or slow responses appear.
- Re-run `DetectWebsiteRedesignJob` for a handful of organizations after touching thresholds to confirm the detected months line up with real-world rebuilds.

## Triggering Detection Locally

- Start a queue worker so the detector can run asynchronously:
  ```bash
  php artisan queue:work
  ```
- In another terminal, dispatch the job for the organization you want to refresh:
  ```bash
  php artisan tinker
  >>> App\Jobs\DetectWebsiteRedesignJob::dispatch($organizationId);
  ```
- When changing schema or detector behaviour, run `php artisan migrate:fresh` (or the relevant migrations) before dispatching jobs so the new before/after fields exist in the database.

### Status Reference

| Status            | Meaning                                                                                                                                         | Typical Message                                                                   | Next Steps                                                                                                        |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `success`         | Wayback returned data and we found at least one navigation overhaul.                                                                            | `null` (no banner)                                                                | Nothing to do; redesign data is current.                                                                          |
| `no_wayback_data` | The CDX API returned no usable snapshots (Wayback never crawled the site, or every capture was filtered out due to size/format constraints).    | “Wayback Machine did not return any snapshots.”                                   | Verify the site exists in Wayback; consider loosening snapshot length filters if the captures look legitimate.   |
| `no_major_events` | Wayback responded but every usable snapshot kept essentially the same navigation, so no redesign-worthy change was detected.                    | “Navigation analysis did not detect any major redesigns.”                         | Lower the change threshold or inspect the nav HTML manually if you expect a rebuild that our heuristics missed.   |
| `wayback_failed`  | The CDX or HTML fetch request failed (timeout, network issue, non-200 response).                                                                | “Wayback request failed…” (includes HTTP status or cURL error)                    | Retry later; increase timeout or review connectivity if failures persist.                                        |
