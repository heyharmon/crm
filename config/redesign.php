<?php

return [
    'cdx_endpoint' => env('WAYBACK_CDX_ENDPOINT', 'https://web.archive.org/cdx/search/cdx'),
    'min_persistence_days' => (int) env('WAYBACK_MIN_PERSISTENCE_DAYS', 120),
    'max_events' => (int) env('WAYBACK_MAX_REDESIGN_EVENTS', 5),
];
