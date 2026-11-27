<?php

return [
    'media_path' => env('ANNOUNCER_MEDIA_PATH', is_dir('/data') ? '/data/announcer-media' : storage_path('app/private/announcer-media')),
    'timezone' => 'Asia/Jakarta',
];
