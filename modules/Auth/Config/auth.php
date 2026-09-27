<?php

declare(strict_types=1);

// Merged into Laravel's own `auth` config key, so it reads as
// `config('auth.registration_enabled')`.
return [
    // Closed by default: on a public address an open `register` endpoint lets
    // anyone create an account and run pipelines on the instance.
    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', false),
];
