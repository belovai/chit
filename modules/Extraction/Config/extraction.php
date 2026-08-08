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
        // Binds FakeDocumentAi instead of the real DocumentAi. Tests only —
        // provider, model, token budget and key all live on the user's credential.
        'fake_documents' => env('EXTRACTION_FAKE_DOCUMENTS', false),
    ],
];
