<?php

return [
    'cdx_endpoint' => env('WAYBACK_CDX_ENDPOINT', 'https://web.archive.org/cdx/search/cdx'),
    'min_persistence_days' => (int) env('WAYBACK_MIN_PERSISTENCE_DAYS', 120),
    'max_events' => (int) env('WAYBACK_MAX_REDESIGN_EVENTS', 5),
    'allowed_status_codes' => array_values(array_filter(array_map(
        static function ($value) {
            $code = (int) trim((string) $value);

            return $code > 0 ? $code : null;
        },
        explode(',', env('WAYBACK_ALLOWED_STATUS_CODES', '200'))
    ))),
    'allowed_mimetypes' => array_values(array_filter(array_map(
        static function ($value) {
            $type = strtolower(trim((string) $value));

            return $type !== '' ? $type : null;
        },
        explode(',', env('WAYBACK_ALLOWED_MIMETYPES', 'text/html'))
    ))),
    'min_payload_bytes' => (int) env('WAYBACK_MIN_PAYLOAD_BYTES', 10240),
];
