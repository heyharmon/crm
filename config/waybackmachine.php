<?php

/*
|--------------------------------------------------------------------------
| Wayback Redesign Tuning
|--------------------------------------------------------------------------
| Wayback returns “collapsed digest windows” – each window represents a
| stretch of time where the archived HTML digest stays unchanged. We treat
| a window as a major redesign only when it lives long enough
| (`min_persistence_days`) and differs enough from the previous window
| (`min_payload_change_ratio`).
|
| Tuning tips:
| - Lower `min_persistence_days` when Wayback captures are sparse; this lets
|   shorter-lived windows qualify so the latest redesign does not get skipped.
|   Raising it does the opposite: only very stable windows survive.
| - Lower `min_payload_change_ratio` (e.g. from 0.30 → 0.15) if genuine
|   redesigns reuse large portions of markup. Increase it when minor content
|   tweaks still slip through.
| - Adjust `min_payload_bytes` to admit or exclude lightweight responses;
|   dropping it pulls more captures into each window and can improve the
|   median payload signal at the expense of more noise.
| - Raise `max_events` to keep a deeper history of redesign windows; lowering
|   it keeps storage small but prunes older windows sooner.
|
| When you tweak these values, re-run the redesign job for a few
| organizations to confirm the detected windows align with real-world
| redesigns before promoting the change more broadly.
*/

return [
    // Base CDX endpoint used to pull collapsed snapshot metadata from Wayback.
    'cdx_endpoint' => env('WAYBACK_CDX_ENDPOINT', 'https://web.archive.org/cdx/search/cdx'),

    // Minimum number of days a digest must remain stable before we treat it as a major redesign.
    'min_persistence_days' => (int) env('WAYBACK_MIN_PERSISTENCE_DAYS', 20),

    // Minimum payload size in bytes; smaller captures (often WAF challenges) are ignored.
    'min_payload_bytes' => (int) env('WAYBACK_MIN_PAYLOAD_BYTES', 8192),

    // Cap on how many redesign events we keep per organization (newest events win).
    'max_events' => (int) env('WAYBACK_MAX_REDESIGN_EVENTS', 20),

    // Minimum relative change (e.g. 0.3 = 30%) between consecutive stable payload medians to count as a major redesign.
    'min_payload_change_ratio' => (float) env('WAYBACK_MIN_PAYLOAD_CHANGE_RATIO', 0.15),

    // Optional delay (milliseconds) inserted before calling Wayback to avoid hammering their API.
    'request_delay_ms' => (int) env('WAYBACK_REQUEST_DELAY_MS', 1000),

    // Timeout (seconds) for each Wayback API request; bumps default beyond 20s to avoid cURL 28 timeouts.
    'request_timeout_seconds' => (int) env('WAYBACK_REQUEST_TIMEOUT_SECONDS', 60),

    // Only snapshots with these HTTP status codes are considered; others are discarded as noise.
    'allowed_status_codes' => array_values(array_filter(array_map(
        static function ($value) {
            $code = (int) trim((string) $value);

            return $code > 0 ? $code : null;
        },
        explode(',', env('WAYBACK_ALLOWED_STATUS_CODES', '200'))
    ))),

    // Restrict captures to specific mimetypes (defaults to HTML) to avoid assets/JSON/etc.
    'allowed_mimetypes' => array_values(array_filter(array_map(
        static function ($value) {
            $type = strtolower(trim((string) $value));

            return $type !== '' ? $type : null;
        },
        explode(',', env('WAYBACK_ALLOWED_MIMETYPES', 'text/html'))
    ))),

];
