<?php

declare(strict_types=1);

return [
    // How long a run may sit in `awaiting_manual` before ExpireStaleRuns closes it.
    'gate' => [
        'expire_after_days' => env('PIPELINE_GATE_EXPIRE_AFTER_DAYS', 30),
    ],

    // How long binary artifacts survive after the run reaches a terminal state.
    // Structured artifacts (json/text) are never pruned — they are the audit trail.
    'artifact_retention_days' => env('PIPELINE_ARTIFACT_RETENTION_DAYS', 30),

    'retry' => [
        'base_backoff_seconds' => env('PIPELINE_RETRY_BASE_BACKOFF', 30),
        'max_backoff_seconds' => env('PIPELINE_RETRY_MAX_BACKOFF', 600),
    ],

    'queues' => [
        'default' => env('PIPELINE_QUEUE_DEFAULT', 'pipeline-default'),
        'cpu' => env('PIPELINE_QUEUE_CPU', 'pipeline-cpu'),
        'ai' => env('PIPELINE_QUEUE_AI', 'pipeline-ai'),
    ],

    'demo' => [
        'enabled' => env('PIPELINE_DEMO_ENABLED', true),
    ],
];
