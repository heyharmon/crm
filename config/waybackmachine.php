<?php

return [
    // Base CDX endpoint used to pull collapsed snapshot metadata from Wayback.
    'cdx_endpoint' => env('WAYBACK_CDX_ENDPOINT', 'https://web.archive.org/cdx/search/cdx'),

    // Minimum number of days (default 120) a digest must remain stable before we treat it as a major redesign.
    'min_persistence_days' => (int) env('WAYBACK_MIN_PERSISTENCE_DAYS', 80),

    // Minimum payload size in bytes (default 10240); smaller captures (often WAF challenges) are ignored.
    'min_payload_bytes' => (int) env('WAYBACK_MIN_PAYLOAD_BYTES', 20480),

    // Cap on how many redesign events (default 5) we keep per organization (newest events win).
    'max_events' => (int) env('WAYBACK_MAX_REDESIGN_EVENTS', 5),

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
