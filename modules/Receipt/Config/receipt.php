<?php

declare(strict_types=1);

return [
    'upload' => [
        'max_size_kb' => env('RECEIPT_UPLOAD_MAX_KB', 20480),
        'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'],
        'disk' => env('RECEIPT_DISK', 'local'),
    ],

    'gate' => [
        // Below this extraction confidence the run always stops for a human.
        'min_confidence' => [
            'receipt' => env('RECEIPT_GATE_MIN_CONFIDENCE', 0.75),
            'utility_bill' => env('RECEIPT_GATE_MIN_CONFIDENCE_BILL', 0.80),
        ],

        // A step proposes a severity; this table overrides it. Tightening or
        // loosening the review flow is a config change, never a code change.
        'severity' => [
            'exact_duplicate' => 'blocker',
            'possible_duplicate' => 'blocker',
            'line_items_sum_mismatch' => 'blocker',
            'total_missing' => 'blocker',
            'date_in_future' => 'blocker',
            'classification_uncertain' => 'blocker',
            'classification_conflict' => 'blocker',
            'meter_reading_decreased' => 'blocker',
            'merchant_ambiguous' => 'warning',
            'new_merchant' => 'warning',
            'low_ocr_confidence' => 'warning',
            'consumption_anomaly' => 'warning',
            'period_gap' => 'warning',
            'no_previous_bill' => 'info',
        ],

        // Start strict. Raise this as trust in the extraction grows — at 0 a
        // single warning stops the run, which is what you want on day one.
        'max_warnings' => env('RECEIPT_GATE_MAX_WARNINGS', 0),
    ],

    'matching' => [
        // Below this the merchant is treated as new rather than matched.
        'merchant_accept_score' => env('RECEIPT_MERCHANT_ACCEPT_SCORE', 0.80),
        // Two candidates within this distance of each other are ambiguous.
        'merchant_ambiguity_margin' => env('RECEIPT_MERCHANT_AMBIGUITY_MARGIN', 0.10),
        'product_accept_score' => env('RECEIPT_PRODUCT_ACCEPT_SCORE', 0.75),
    ],

    'anomaly' => [
        // Consumption more than N× the same-period average is suspicious.
        'consumption_factor' => env('RECEIPT_ANOMALY_CONSUMPTION_FACTOR', 3.0),
        // A gap larger than this between billing periods is worth flagging.
        'period_gap_days' => env('RECEIPT_ANOMALY_PERIOD_GAP_DAYS', 45),
    ],

    'validation' => [
        // Items may miss the printed total by this much (minor units) before it
        // counts as a mismatch — rounding and unpriced deposits are normal.
        'sum_tolerance_minor' => env('RECEIPT_SUM_TOLERANCE_MINOR', 200),
    ],
];
