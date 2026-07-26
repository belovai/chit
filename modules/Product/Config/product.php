<?php

declare(strict_types=1);

return [
    'matching' => [
        'threshold' => env('PRODUCT_MATCH_THRESHOLD', 0.3),
        'limit' => env('PRODUCT_MATCH_LIMIT', 5),
    ],
];
