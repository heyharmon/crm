# Website Redesign Detection

The redesign detector identifies the most recent major rebuild of an organization's website by analyzing historical snapshots from the Wayback Machine using statistical analysis to detect significant design changes.

## How It Works

The detection process follows a comprehensive methodology:

1. **Snapshot Acquisition** – Queries the Wayback Machine CDX API for all successful HTML captures of the website, then samples to one snapshot per 6-month period (H1: Jan-Jun, H2: Jul-Dec) to focus on major changes rather than minor updates.

2. **Feature Extraction** – For each snapshot, extracts three key features:

    - HTML tag frequency (structural architecture)
    - CSS class frequency (design and component systems)
    - Head asset URLs (stylesheets and scripts)

3. **Difference Scoring** – Compares consecutive snapshots using:

    - Normalized difference score (Bray-Curtis variation) for tag and class frequencies
    - Jaccard distance for asset URL sets

4. **Composite Analysis** – Combines individual metrics into a weighted composite score:

    - CSS classes: 50% (strongest signal of design changes)
    - HTML tags: 25%
    - Head assets: 25%
    - Calculates statistical baseline (mean + standard deviation) from all composite scores

5. **Redesign Prediction** – Identifies periods where composite score exceeds the statistical threshold (mean + 1 standard deviation), selecting the most recent major redesign event.

## Data Model

-   **organization_website_redesigns** – one row per detected redesign event. Columns include:
    -   `before_wayback_timestamp` / `before_captured_at` – snapshot before redesign
    -   `after_wayback_timestamp` / `after_captured_at` – snapshot after redesign
    -   `tag_difference_score` – normalized difference in HTML tag frequencies
    -   `class_difference_score` – normalized difference in CSS class frequencies
    -   `asset_difference_score` – Jaccard distance between asset sets
    -   `composite_score` – weighted combination of all difference scores
    -   `statistical_threshold` – calculated threshold (mean + stddev) for this website
    -   `before_tag_counts` / `after_tag_counts` – frequency maps of HTML tags
    -   `before_html_class_count` / `after_html_class_count` – unique CSS class counts
    -   `before_body_class_count` / `after_body_class_count` – total CSS class usage
    -   `before_head_asset_count` / `after_head_asset_count` – head asset counts
    -   `before_html_classes` / `after_html_classes` – arrays of CSS class names
    -   `before_body_classes` / `after_body_classes` – arrays of CSS class names
    -   `before_head_assets` / `after_head_assets` – arrays of asset URLs
    -   `nav_similarity` – (legacy field, not used)
    -   `before_head_html` / `after_head_html` – (legacy fields, not used)
-   **organizations.last_major_redesign_at** – cached date of the detected redesign event
-   **organizations.website_redesign_status** / **website_redesign_status_message** – status from the most recent job run

## Backend Flow

1. **DetectWebsiteRedesignJob** (`app/Jobs/DetectWebsiteRedesignJob.php`)
    - Loads the organization and exits early when the record or website URL is missing
    - Fetches historical snapshots from Wayback Machine CDX API
    - Samples snapshots to one per 6-month period
    - Extracts features (tags, classes, assets) from each snapshot's HTML
    - Calculates difference scores between consecutive snapshots
    - Performs statistical analysis to establish baseline and threshold
    - Identifies and stores the most recent major redesign event
    - Updates the organization's status and `last_major_redesign_at` field

## Configuration

No external API configuration is required. The job directly queries the Wayback Machine's public CDX API.

The job has an extended timeout of 1 hour (`timeout = 3600`) to accommodate the extensive analysis process.

## Frontend Notes

-   The organization detail panel lists the detected redesign with its capture date and paired "before" and "after" screenshots (linking directly to the Wayback snapshots). When the redesign job fails or produces no results, the status banner explains why.

## Triggering Detection Locally

-   Start a queue worker so the detector can run asynchronously:
    ```bash
    php artisan queue:work
    ```
-   In another terminal, dispatch the job for the organization you want to refresh:
    ```bash
    php artisan tinker
    >>> App\Jobs\DetectWebsiteRedesignJob::dispatch($organizationId);
    ```

### Status Reference

| Status            | Meaning                                                                   | Typical Message                                                                                                                            | Next Steps                                                                                                            |
| ----------------- | ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------- |
| `success`         | Analysis completed and a redesign was detected.                           | `null` (no banner)                                                                                                                         | Nothing to do; redesign data is current.                                                                              |
| `no_major_events` | Analysis completed but no major redesign was detected.                    | "No major redesigns detected" or "No historical snapshots found" or "Insufficient snapshots for analysis" or "Unable to analyze snapshots" | The website may not have had a significant redesign, insufficient historical data, or snapshots couldn't be analyzed. |
| `api_failed`      | The analysis failed due to an error (network issue, parsing error, etc.). | "Analysis failed…" (includes error details)                                                                                                | Check logs for details. Verify the website URL is accessible and has Wayback Machine snapshots.                       |

## Technical Details

### Snapshot Sampling Strategy

The 6-month sampling period is crucial for distinguishing major redesigns from minor updates:

-   Smooths out noise from weekly content updates or small component tweaks
-   Makes large-scale changes associated with full redesigns more prominent
-   Provides sufficient data points for statistical analysis while keeping processing time reasonable

### Statistical Threshold

The threshold (mean + 1 standard deviation) is calculated uniquely for each website based on its own historical change patterns. This approach:

-   Adapts to different website update frequencies
-   Identifies changes that are statistically significant for that specific site
-   Avoids false positives from sites with naturally high change rates

### Composite Score Weighting

CSS classes receive the highest weight (50%) because:

-   Class name changes directly correspond to design system changes
-   Framework migrations (e.g., Bootstrap to Tailwind) are clearly visible
-   Component library updates are easily detected

HTML tags (25%) and head assets (25%) provide supporting evidence of structural and technical stack changes.
