<?php

declare(strict_types=1);

namespace Modules\Receipt\Requests;

use App\Traits\HasCodedValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Extraction\Enums\DocumentType;

final class UploadReceiptRequest extends FormRequest
{
    use HasCodedValidationMessages;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('receipt.upload.max_size_kb'),
                'mimetypes:'.implode(',', (array) config('receipt.upload.mimes')),
            ],
            'doc_type_hint' => ['sometimes', 'nullable', Rule::enum(DocumentType::class)],
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
