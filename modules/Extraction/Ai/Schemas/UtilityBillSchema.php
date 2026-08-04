<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Schemas;

final class UtilityBillSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'provider_name', 'customer_reference', 'currency', 'total_minor',
                'issued_at', 'period_start', 'period_end',
                'meter_reading', 'previous_meter_reading', 'consumption', 'consumption_unit',
                'confidence',
            ],
            'properties' => [
                'provider_name' => ['type' => ['string', 'null'], 'description' => 'The utility company as printed.'],
                'customer_reference' => ['type' => ['string', 'null'], 'description' => 'Customer/account number (ügyfélszám, szerződéses folyószámla). This is what links a bill to its predecessor — copy it exactly, digits only.'],
                'currency' => ['type' => ['string', 'null']],
                'total_minor' => ['type' => ['integer', 'null'], 'description' => 'Amount payable in MINOR units.'],
                'issued_at' => ['type' => ['string', 'null'], 'description' => 'Invoice date as YYYY-MM-DD.'],
                'period_start' => ['type' => ['string', 'null'], 'description' => 'First day of the billing period, YYYY-MM-DD.'],
                'period_end' => ['type' => ['string', 'null'], 'description' => 'Last day of the billing period, YYYY-MM-DD.'],
                'meter_reading' => ['type' => ['number', 'null'], 'description' => 'Closing meter reading for this period.'],
                'previous_meter_reading' => ['type' => ['number', 'null'], 'description' => 'Opening meter reading, if the bill prints it.'],
                'consumption' => ['type' => ['number', 'null'], 'description' => 'Consumption for the period.'],
                'consumption_unit' => ['type' => ['string', 'null'], 'description' => 'kWh, m3, GJ …'],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }
}
