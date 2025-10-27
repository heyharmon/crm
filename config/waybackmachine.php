<?php

/*
|--------------------------------------------------------------------------
| Wayback Redesign Tuning
|--------------------------------------------------------------------------
 | The redesign detector samples one snapshot per year, compares the site’s
 | shell signature (html/body classes + head assets), and drills into monthly
 | captures to locate the month
| a major rebuild shipped. Tune these knobs to balance accuracy vs. runtime:
|
| - `nav_similarity_change_threshold` (0-1) governs how different the yearly
|   signatures must be before we consider a redesign window. Lowering it will
|   surface more candidate windows; raising it filters out subtle class/markup tweaks.
| - `nav_similarity_match_threshold` (0-1) is used during the monthly pass to
|   confirm the first “after” snapshot. Increase it if noisy captures are being
|   misclassified, or reduce it when the new nav is similar but not identical.
| - `min_snapshot_length_bytes` filters out tiny HTML payloads (e.g. WAF
|   challenges). Drop it when legitimate sites ship very lean markup.
| - `max_snapshot_results` caps how many snapshot rows we request from
|   Wayback during both the yearly and monthly passes. Increase it for
|   long-running sites with deep archives.
| - `max_events` controls how many redesign rows we store per organization
|   (newest entries win). Each row keeps the last snapshot before and first
|   snapshot after the detected redesign.
| - `request_delay_ms` inserts a small pause before each HTML fetch so we
|   stay polite with the archive. Keep it non-zero in production jobs.
| - `request_timeout_seconds` is shared by both the CDX metadata request and the
|   HTML fetch; raise it if you see frequent timeouts on the queue worker.
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
