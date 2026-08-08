<?php

declare(strict_types=1);

namespace Modules\Ai\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Registries\ProviderRegistry;

final class UpdateAiCredential
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(AiCredential $credential, array $data): AiCredential
    {
        $attributes = [];

        if (array_key_exists('label', $data)) {
            $attributes['label'] = $data['label'];
        }

        if (array_key_exists('settings', $data)) {
            $attributes['settings'] = $data['settings'];
        }

        $key = is_string($data['api_key'] ?? null) ? $data['api_key'] : null;
        $model = is_string($data['model'] ?? null) ? $data['model'] : null;

        // A new key or a different model invalidates the previous verification,
        // so re-prove both before accepting the change.
        if ($key !== null || $model !== null) {
            $verification = $this->providers->get($credential->provider)->verify(
                $key ?? $credential->api_key,
                $model ?? $credential->model,
            );

            if (!$verification->ok) {
                throw ValidationException::withMessages([
                    ($key !== null ? 'api_key' : 'model') => $verification->message
                        ?? 'The provider rejected this configuration.',
                ]);
            }

            $attributes['status'] = CredentialStatus::Verified;
            $attributes['last_verified_at'] = now();
            $attributes['failure_count'] = 0;
            $attributes['last_error'] = null;

            if ($key !== null) {
                $attributes['api_key'] = $key;
                $attributes['key_last_four'] = AiCredential::lastFour($key);
                $attributes['key_fingerprint'] = AiCredential::fingerprint($key);
            }

            if ($model !== null) {
                $attributes['model'] = $model;
            }
        }

        $credential->update($attributes);

        return $credential->refresh();
    }
}
