<?php

return [
    'key_id' => env('B2_KEY_ID'),
    'application_key' => env('B2_APPLICATION_KEY'),
    'bucket_id' => env('B2_BUCKET_ID'),
    'bucket_name' => env('B2_BUCKET_NAME'),
    'signed_url_ttl_seconds' => (int) env('B2_SIGNED_URL_TTL_SECONDS', 3600),
    'allowed_prefix' => env('B2_ALLOWED_PREFIX'),
];

?>
