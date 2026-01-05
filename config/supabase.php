<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supabase Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk integrasi Supabase (jika diperlukan untuk API calls
    | langsung ke Supabase, misalnya untuk Storage atau Realtime)
    |
    */

    'url' => env('SUPABASE_URL', ''),
    'key' => env('SUPABASE_KEY', ''),
    'service_key' => env('SUPABASE_SERVICE_KEY', ''),
];
