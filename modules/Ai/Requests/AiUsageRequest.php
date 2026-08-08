<?php

declare(strict_types=1);

namespace Modules\Ai\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AiUsageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }
}
