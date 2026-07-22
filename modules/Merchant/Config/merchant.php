<?php

declare(strict_types=1);

return [
    'matching' => [
        'threshold' => (float) env('MERCHANT_MATCH_THRESHOLD', 0.3),
        'limit' => (int) env('MERCHANT_MATCH_LIMIT', 5),
    ],
];
