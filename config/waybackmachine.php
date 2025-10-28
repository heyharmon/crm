<?php

/*
|--------------------------------------------------------------------------
| Wayback Redesign Tuning
|--------------------------------------------------------------------------
 | The redesign detector samples one snapshot per year, compares the site’s
| shell signature (html/body classes), and drills into monthly captures to locate
| the month a major rebuild shipped. Tune these knobs to balance accuracy vs.
| runtime:
*/

return [
    'cdx_endpoint' => env('WAYBACK_CDX_ENDPOINT', 'https://web.archive.org/cdx/search/cdx'),

    // (0-1) Default: 70, Governs how different the yearly signatures must be before we consider a redesign window. Lowering it will surface more candidate windows; raising it filters out subtle class tweaks.
    'nav_similarity_change_threshold' => (float) env('WAYBACK_NAV_SIMILARITY_CHANGE_THRESHOLD', 0.45),

    // (0-1) Default: 78, Used during the monthly pass to confirm the first “after” snapshot. Increase it if noisy captures are being misclassified, or reduce it when the new nav is similar but not identical.
    'nav_similarity_match_threshold' => (float) env('WAYBACK_NAV_SIMILARITY_MATCH_THRESHOLD', 0.70),

    // Class-change safeguard knobs: Adjust these when you want to ignore tiny tweaks (raise the numbers) or capture subtle rebuilds (lower them).
    // - The detector counts how many CSS classes appear, disappear, or change between snapshots.
    // - "Delta" numbers require a minimum count of classes that must change. Think: how many uniforms swapped colours.
    // - "Ratio" numbers describe what fraction of the total classes changed (0.25 = one quarter of them).
    'min_total_class_delta' => (int) env('WAYBACK_MIN_TOTAL_CLASS_DELTA', 3), // Require at least this many class changes in total (html + body combined) before flagging a redesign.
    'min_total_class_change_ratio' => (float) env('WAYBACK_MIN_TOTAL_CLASS_CHANGE_RATIO', 0.20), // Require this fraction of all classes to change (0.35 = 35%).
    'min_html_class_delta' => (int) env('WAYBACK_MIN_HTML_CLASS_DELTA', 0), // Optional guard for <html> classes; leave at 0 when that tag rarely carries useful classes.
    'min_html_class_change_ratio' => (float) env('WAYBACK_MIN_HTML_CLASS_CHANGE_RATIO', 0.0), // Ratio versions of the html/body rules (0 = off). Helpful when class counts vary between domains.
    'min_body_class_delta' => (int) env('WAYBACK_MIN_BODY_CLASS_DELTA', 2), // Minimum number of <body> classes that must change; bump this up to ignore tiny tweaks.
    'min_body_class_change_ratio' => (float) env('WAYBACK_MIN_BODY_CLASS_CHANGE_RATIO', 0.15),

    // Default: 8192 Filters out tiny HTML payloads (e.g. WAF challenges). Drop it when legitimate sites ship very lean markup.
    'min_snapshot_length_bytes' => (int) env('WAYBACK_MIN_SNAPSHOT_LENGTH_BYTES', 8192),

    // Default 2000, Caps how many snapshot rows we request from Wayback during both the yearly and monthly passes. Increase it for long-running sites with deep archives.
    'max_snapshot_results' => (int) env('WAYBACK_MAX_SNAPSHOT_RESULTS', 2000),

    // Default 10: Controls how many redesign rows we store per organization (newest entries win). Each row keeps the last snapshot before and first snapshot after the detected redesign.
    'max_events' => (int) env('WAYBACK_MAX_REDESIGN_EVENTS', 20),

    // Inserts a small pause before each HTML fetch so we stay polite with the archive. Keep it non-zero in production jobs.
    'request_delay_ms' => (int) env('WAYBACK_REQUEST_DELAY_MS', 1500),

    // Is shared by both the CDX metadata request and the HTML fetch; raise it if you see frequent timeouts on the queue worker.
    'request_timeout_seconds' => (int) env('WAYBACK_REQUEST_TIMEOUT_SECONDS', 300),
];
