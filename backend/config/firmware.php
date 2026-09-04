<?php

return [
    'disk' => env('FIRMWARE_DISK', 'local'),
    'max_upload_kilobytes' => (int) env('FIRMWARE_MAX_UPLOAD_KB', 10240),
    'extensions' => ['bin', 'hex', 'uf2', 'zip'],
];
