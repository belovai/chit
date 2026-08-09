<?php

declare(strict_types=1);

namespace Modules\Ai\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Ai\Registries\ProviderRegistry;
use Modules\Ai\Rules\UniqueApiKeyForProvider;
use Modules\Ai\Rules\ValidProviderModel;
use Modules\Ai\Rules\ValidProviderSettings;

final class StoreAiCredentialRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function messages(): array
    {
        return $this->codedValidationMessages();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(ProviderRegistry $providers): array
    {
        $providerId = $this->string('provider')->toString();
        $ownerId = (int) $this->user()?->getAuthIdentifier();

        return [
            'provider' => [
                'required',
                'string',
                Rule::in(array_map(fn ($provider): string => $provider->id(), $providers->all())),
            ],
            'label' => ['required', 'string', 'max:255'],
            'api_key' => [
                'required',
                'string',
                'min:8',
                'max:512',
                new UniqueApiKeyForProvider($ownerId, $providerId),
            ],
            'model' => ['required', 'string', new ValidProviderModel($providers, $providerId)],
            'settings' => ['required', 'array', new ValidProviderSettings($providers, $providerId)],
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        if ($this->filled('api_key')) {
            $this->merge(['api_key' => $this->string('api_key')->trim()->toString()]);
        }
    }
}
