# Website Redesign Detection

The redesign detector identifies the most recent major rebuild of an organization's website by calling the Design Decoder API, which analyzes Wayback Machine snapshots to predict when significant design changes occurred.

## How It Works

1. **API Integration** – The `DetectWebsiteRedesignJob` calls the Design Decoder API with the organization's website URL.
2. **Analysis Result** – The API returns a predicted redesign timestamp along with the timestamp before the redesign, based on analysis of historical snapshots.
3. **Event Persistence** – The job stores the redesign event with the before and after timestamps, updating the organization's `last_major_redesign_at` field.

## Data Model

-   **organization_website_redesigns** – one row per detected redesign event. Columns include:
    -   `before_wayback_timestamp` / `before_captured_at`
    -   `after_wayback_timestamp` / `after_captured_at`
    -   `nav_similarity` (not used with Design Decoder API)
    -   `before_html_class_count` / `after_html_class_count` (not used with Design Decoder API)
    -   `before_body_class_count` / `after_body_class_count` (not used with Design Decoder API)
    -   `before_html_classes` / `after_html_classes` (not used with Design Decoder API)
    -   `before_body_classes` / `after_body_classes` (not used with Design Decoder API)
-   **organizations.last_major_redesign_at** – cached date of the detected redesign event.
-   **organizations.website_redesign_status** / **website_redesign_status_message** – status from the most recent job run so the UI can surface "API failed" or "No redesigns detected" states.

## Backend Flow

1. **DetectWebsiteRedesignJob** (`app/Jobs/DetectWebsiteRedesignJob.php`)
    - Loads the organization and exits early when the record or website URL is missing.
    - Calls the Design Decoder API with the organization's website URL.
    - Processes the API response and stores the redesign event.
    - Updates the organization's status and `last_major_redesign_at` field.

## Configuration

The Design Decoder API is configured in `config/designdecoder.php`:

```php
return [
    'api_url' => env('DESIGN_DECODER_API_URL'),
    'timeout' => (int) env('DESIGN_DECODER_TIMEOUT', 300),
];
```

Add these environment variables to your `.env` file:

```
DESIGN_DECODER_API_URL=https://your-design-decoder-domain.com
DESIGN_DECODER_TIMEOUT=300
```

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

| Status            | Meaning                                                                                    | Typical Message                                | Next Steps                                                                                        |
| ----------------- | ------------------------------------------------------------------------------------------ | ---------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `success`         | The API returned data and a redesign was detected.                                         | `null` (no banner)                             | Nothing to do; redesign data is current.                                                          |
| `no_major_events` | The API responded but no major redesign was detected.                                      | "No major redesigns detected"                  | The website may not have had a significant redesign, or the analysis couldn't detect one.         |
| `api_failed`      | The API request failed (timeout, network issue, non-200 response, or configuration error). | "API request failed…" (includes error details) | Verify the API URL is configured correctly and the service is accessible. Check logs for details. |
