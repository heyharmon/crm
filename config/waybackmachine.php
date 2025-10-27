<?php

/*
|--------------------------------------------------------------------------
| Wayback Redesign Tuning
|--------------------------------------------------------------------------
| The redesign detector samples one snapshot per year, compares the site’s
| navigation structure, and drills into monthly captures to locate the month
| a major rebuild shipped. Tune these knobs to balance accuracy vs. runtime:
|
| - Lower `nav_similarity_change_threshold` to make the detector less strict
|   about what counts as a redesign; raise it if we flag too many minor tweaks.
| - Raise `nav_similarity_match_threshold` when back-to-back navigation
|   snapshots look noisy and you need a stronger signal before declaring the
|   “new” design stable.
| - `min_snapshot_length_bytes` filters out tiny HTML payloads (e.g. WAF
|   challenges). Drop it when legitimate sites ship very lean markup.
| - `max_snapshot_results` caps how many snapshot rows we request from
|   Wayback during year/month passes. Increase it for long-running sites.
| - `max_events` controls how many redesign rows we store per organization
|   (newest entries win). Each row keeps the last snapshot before and first
|   snapshot after the detected redesign.
| - `request_delay_ms` inserts a small pause before each HTML fetch so we
|   stay polite with the archive. Keep it non-zero in production jobs.
*/

return [
    'cdx_endpoint' => env('WAYBACK_CDX_ENDPOINT', 'https://web.archive.org/cdx/search/cdx'),

    // Similarity thresholds used to decide when navigation changes represent a redesign.
    'nav_similarity_change_threshold' => (float) env('WAYBACK_NAV_SIMILARITY_CHANGE_THRESHOLD', 0.6),
    'nav_similarity_match_threshold' => (float) env('WAYBACK_NAV_SIMILARITY_MATCH_THRESHOLD', 0.78),

    // Snapshot hygiene + volume controls.
    'min_snapshot_length_bytes' => (int) env('WAYBACK_MIN_SNAPSHOT_LENGTH_BYTES', 8192),
    'max_snapshot_results' => (int) env('WAYBACK_MAX_SNAPSHOT_RESULTS', 2000),
    'max_events' => (int) env('WAYBACK_MAX_REDESIGN_EVENTS', 10),

    // Wayback request behaviour.
    'request_delay_ms' => (int) env('WAYBACK_REQUEST_DELAY_MS', 750),
    'request_timeout_seconds' => (int) env('WAYBACK_REQUEST_TIMEOUT_SECONDS', 120),
];
