<?php

declare(strict_types=1);

namespace Modules\Ai\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Registries\ProviderRegistry;
use Modules\Ai\Rules\ValidProviderModel;
use Modules\Ai\Rules\ValidProviderSettings;

final class StoreAiCredentialRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(ProviderRegistry $providers): array
    {
        $providerId = $this->string('provider')->toString();

        return [
            'provider' => [
                'required',
                'string',
                Rule::in(array_map(fn ($provider): string => $provider->id(), $providers->all())),
            ],
            'label' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'min:8', 'max:512'],
            'model' => ['required', 'string', new ValidProviderModel($providers, $providerId)],
            'settings' => ['required', 'array', new ValidProviderSettings($providers, $providerId)],
        ];
    }

    /**
     * Duplicate detection cannot be a `Rule::unique` on `api_key`: that column
     * is encrypted, so no two rows holding the same key share a ciphertext.
     * The comparison has to happen on the fingerprint of the submitted key.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $key = $this->string('api_key')->trim()->toString();

            if ($key === '') {
                return;
            }

            $exists = AiCredential::query()
                ->forUser((int) $this->user()?->getAuthIdentifier())
                ->where('provider', $this->string('provider')->toString())
                ->where('key_fingerprint', AiCredential::fingerprint($key))
                ->exists();

            if ($exists) {
                $validator->errors()->add('api_key', 'This API key is already stored for this provider.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('api_key')) {
            $this->merge(['api_key' => $this->string('api_key')->trim()->toString()]);
        }
    }
}
