<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Schemas;

/**
 * Provider-neutral JSON Schema. Every provider is expected to enforce this
 * shape with whatever mechanism it offers; only the enforcement differs.
 *
 * Structured-output schemas must set additionalProperties: false and list every
 * key in `required` — optional keys are expressed as a nullable type, not by
 * omission from `required`.
 */
final class ClassificationSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['document_type', 'confidence', 'reason'],
            'properties' => [
                'document_type' => [
                    'type' => 'string',
                    'enum' => ['receipt', 'utility_bill', 'unknown'],
                    'description' => 'receipt = a shop or restaurant receipt; utility_bill = a periodic bill from a utility provider (electricity, gas, water, heating, telecom); unknown = neither.',
                ],
                'confidence' => [
                    'type' => 'number',
                    'description' => 'How certain you are, from 0 to 1.',
                ],
                'reason' => [
                    'type' => ['string', 'null'],
                    'description' => 'One short sentence naming the evidence you used.',
                ],
            ],
        ];
    }
}
