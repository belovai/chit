<?php

declare(strict_types=1);

namespace Modules\Pipeline\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\TriggerSource;

final class IndexPipelineRunsRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(RunStatus::class)],
            'trigger_source' => ['sometimes', Rule::enum(TriggerSource::class)],
            'definition_key' => ['sometimes', 'string', 'max:64'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->codedValidationMessages();
    }
}
