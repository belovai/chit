<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Schemas;

final class ReceiptSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['merchant_name', 'merchant_address', 'occurred_at', 'currency', 'total_minor', 'discount_minor', 'payment_method', 'items', 'confidence'],
            'properties' => [
                'merchant_name' => ['type' => ['string', 'null'], 'description' => 'The brand name only, with no branch marker and no company form (KFT., ZRT., BT.).'],
                'merchant_address' => ['type' => ['string', 'null'], 'description' => "The branch's own street address as printed, or null when only the registered office appears."],
                'occurred_at' => ['type' => ['string', 'null'], 'description' => 'Purchase date and time as YYYY-MM-DDTHH:MM:SS. Omit the time part if the receipt has none.'],
                'currency' => ['type' => ['string', 'null'], 'description' => 'ISO 4217 code, e.g. HUF or EUR.'],
                'total_minor' => ['type' => ['integer', 'null'], 'description' => 'Grand total in MINOR units (HUF has no minor unit, so 1327 Ft = 132700). Never a decimal.'],
                'discount_minor' => ['type' => ['integer', 'null'], 'description' => 'Total discount in minor units, as a positive number, or null.'],
                'payment_method' => [
                    'anyOf' => [
                        ['type' => 'string', 'enum' => ['cash', 'card', 'bank_transfer']],
                        ['type' => 'null'],
                    ],
                    'description' => 'How it was paid, if stated.',
                ],
                'items' => [
                    'type' => 'array',
                    'description' => 'One entry per printed line item. Do not merge or invent lines.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['description', 'quantity', 'unit', 'unit_price_minor', 'total_minor'],
                        'properties' => [
                            'description' => ['type' => 'string'],
                            'quantity' => ['type' => 'number'],
                            'unit' => ['type' => ['string', 'null'], 'description' => 'db, kg, l, m3 …'],
                            'unit_price_minor' => ['type' => 'integer'],
                            'total_minor' => ['type' => ['integer', 'null'], 'description' => 'The printed line total, or null when the receipt does not print one.'],
                        ],
                    ],
                ],
                'confidence' => ['type' => 'number', 'description' => 'Your confidence in this extraction as a whole, 0 to 1.'],
            ],
        ];
    }
}
