<?php

declare(strict_types=1);

return [
    // Registers FakeAiProvider instead of the real vendors. Tests set this.
    'fake' => env('AI_FAKE', false),

    // Consecutive authentication failures before a credential is disabled.
    'auth_failure_threshold' => 3,

    // Optional. When set, UserSeeder stores it as the sysadmin's Anthropic key.
    'dev_api_key' => env('AI_DEV_API_KEY'),
];
