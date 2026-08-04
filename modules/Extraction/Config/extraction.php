<?php

declare(strict_types=1);

return [
    'ocr' => [
        'engine' => env('EXTRACTION_OCR_ENGINE', 'tesseract'),
        'binary' => env('EXTRACTION_OCR_BINARY', 'tesseract'),
        // Hungarian first: this is a Hungarian-receipt app. `hun+eng` lets
        // Tesseract fall back on Latin-script product names.
        'languages' => env('EXTRACTION_OCR_LANGUAGES', 'hun+eng'),
        'timeout_seconds' => env('EXTRACTION_OCR_TIMEOUT', 120),
        // Disk used for the module's own scratch files (normalized images).
        'disk' => env('EXTRACTION_DISK', 'local'),
        'pdf_dpi' => env('EXTRACTION_PDF_DPI', 300),
    ],

    'ai' => [
        'provider' => env('EXTRACTION_AI_PROVIDER', 'anthropic'),
        'model' => env('EXTRACTION_AI_MODEL', 'claude-opus-5'),
        'max_tokens' => env('EXTRACTION_AI_MAX_TOKENS', 8000),
        // low/medium/high/xhigh/max — extraction is a structured read, not a
        // reasoning problem, so it does not need a high setting.
        'effort' => env('EXTRACTION_AI_EFFORT', 'low'),
        'api_key' => env('ANTHROPIC_API_KEY'),

        // USD per million tokens, used to turn reported usage into a cost figure.
        // Keyed by model id so switching models does not silently mis-price runs.
        'pricing' => [
            'claude-opus-5' => ['input' => 5.00, 'output' => 25.00, 'cached_input' => 0.50],
            'claude-sonnet-5' => ['input' => 3.00, 'output' => 15.00, 'cached_input' => 0.30],
            'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00, 'cached_input' => 0.10],
        ],
    ],
];
