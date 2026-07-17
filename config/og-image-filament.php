<?php

declare(strict_types=1);

return [
    'disk' => env('OG_IMAGE_DISK', 'public'),
    'directory' => env('OG_IMAGE_DIRECTORY', 'og-images'),
    'node_binary' => env('OG_IMAGE_NODE_BINARY'),
    'chrome_path' => env('OG_IMAGE_CHROME_PATH'),
    'no_sandbox' => env('OG_IMAGE_NO_SANDBOX', false),
];
