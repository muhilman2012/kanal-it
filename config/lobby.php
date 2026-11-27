<?php

return [
    'capture_token' => env('LOBBY_CAPTURE_TOKEN'),
    'media_path' => env('LOBBY_MEDIA_PATH', is_dir('/data') ? '/data/lobby-media' : storage_path('app/private/lobby-media')),
];
