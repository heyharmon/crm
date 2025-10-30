<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Design Decoder API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Design Decoder API integration, which analyzes
    | website redesigns by examining Wayback Machine snapshots.
    |
    */

    'api_url' => env('DESIGN_DECODER_API_URL', 'https://studio--studio-1041920610-4faaa.us-central1.hosted.app'),

    'timeout' => (int) env('DESIGN_DECODER_TIMEOUT', 600),

];
