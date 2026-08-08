<?php

declare(strict_types=1);

namespace Modules\Ai\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ai\Enums\CredentialStatus;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Registries\ProviderRegistry;

final class CreateAiCredential
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ActivateAiCredential $activate,
    ) {}

    /**
     * @param  array{provider: string, label: string, api_key: string, model: string, settings: array<string, mixed>}  $data
     */
    public function handle(int $userId, array $data): AiCredential
    {
        // Verified before anything is written: a key the vendor rejects must
        // not leave a row behind for the user to wonder about.
        $verification = $this->providers->get($data['provider'])->verify($data['api_key'], $data['model']);

        if (!$verification->ok) {
            throw ValidationException::withMessages([
                'api_key' => $verification->message ?? 'The provider rejected this API key.',
            ]);
        }

        return DB::transaction(function () use ($userId, $data): AiCredential {
            $isFirst = !AiCredential::query()->forUser($userId)->exists();

            $credential = AiCredential::query()->create([
                'owner_id' => $userId,
                'provider' => $data['provider'],
                'label' => $data['label'],
                'api_key' => $data['api_key'],
                'key_last_four' => AiCredential::lastFour($data['api_key']),
                'key_fingerprint' => AiCredential::fingerprint($data['api_key']),
                'model' => $data['model'],
                'settings' => $data['settings'],
                'is_active' => false,
                'status' => CredentialStatus::Verified,
                'last_verified_at' => now(),
                'failure_count' => 0,
            ]);

            return $isFirst ? $this->activate->handle($credential) : $credential;
        });
    }
}
