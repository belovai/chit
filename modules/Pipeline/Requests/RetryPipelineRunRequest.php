<?php

declare(strict_types=1);

namespace Modules\Pipeline\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Pipeline\Enums\RetryMode;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Rules\StepKeyInRunRule;

final class RetryPipelineRunRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var PipelineRun $run */
        $run = $this->route('pipelineRun');

        return [
            'mode' => ['required', Rule::enum(RetryMode::class)],
            'step_key' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('mode'), ['single', 'from'], true)),
                'string',
                'max:64',
                new StepKeyInRunRule($run),
            ],
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
