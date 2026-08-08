<?php

declare(strict_types=1);

namespace Modules\Ai\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Registries\ProviderRegistry;
use Modules\Ai\Rules\ValidProviderModel;
use Modules\Ai\Rules\ValidProviderSettings;

/**
 * The provider is immutable: a different vendor is a different credential.
 */
final class UpdateAiCredentialRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->codedValidationMessages();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(ProviderRegistry $providers): array
    {
        /** @var AiCredential $credential */
        $credential = $this->route('credential');

        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'api_key' => ['sometimes', 'string', 'min:8', 'max:512'],
            'model' => ['sometimes', 'string', new ValidProviderModel($providers, $credential->provider)],
            'settings' => ['sometimes', 'array', new ValidProviderSettings($providers, $credential->provider)],
        ];
    }
}
