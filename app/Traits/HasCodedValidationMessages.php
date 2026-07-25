<?php

declare(strict_types=1);

namespace App\Traits;

trait HasCodedValidationMessages
{
    /**
     * Maps Laravel's default validation rule names to stable machine codes.
     * The frontend resolves these to translated, field-aware text — the
     * backend never sends translated prose.
     *
     * @return array<string, string>
     */
    protected function codedValidationMessages(): array
    {
        return [
            'required' => 'required',
            'email' => 'email',
            'string' => 'string',
            'max' => 'max',
            'min' => 'min',
            'unique' => 'unique',
        ];
    }
}
