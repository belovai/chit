<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Support;

use Carbon\CarbonImmutable;
use Modules\Extraction\Ai\ValueObjects\ExtractedBill;
use Modules\Extraction\Ai\ValueObjects\ExtractedLineItem;
use Modules\Extraction\Ai\ValueObjects\ExtractedReceipt;
use Throwable;

/**
 * Turns a schema-shaped payload into DTOs. Deliberately forgiving: a model can
 * violate its own schema, and one bad field must not lose the whole extraction.
 * The gate step decides what a partial result is worth — this layer's job is to
 * hand it over intact.
 */
final class DocumentMapper
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function toReceipt(array $payload): ExtractedReceipt
    {
        /** @var list<array<string, mixed>> $rawItems */
        $rawItems = is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];

        return new ExtractedReceipt(
            merchantName: self::string($payload['merchant_name'] ?? null),
            occurredAt: self::date($payload['occurred_at'] ?? null),
            currency: self::string($payload['currency'] ?? null),
            totalMinor: self::int($payload['total_minor'] ?? null),
            discountMinor: self::int($payload['discount_minor'] ?? null),
            paymentMethod: self::string($payload['payment_method'] ?? null),
            items: array_values(array_map(
                static fn (array $item): ExtractedLineItem => new ExtractedLineItem(
                    description: (string) ($item['description'] ?? ''),
                    quantity: (float) ($item['quantity'] ?? 1),
                    unit: self::string($item['unit'] ?? null),
                    unitPriceMinor: (int) ($item['unit_price_minor'] ?? 0),
                    totalMinor: self::int($item['total_minor'] ?? null),
                ),
                array_filter($rawItems, is_array(...)),
            )),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function toBill(array $payload): ExtractedBill
    {
        return new ExtractedBill(
            providerName: self::string($payload['provider_name'] ?? null),
            customerReference: self::string($payload['customer_reference'] ?? null),
            currency: self::string($payload['currency'] ?? null),
            totalMinor: self::int($payload['total_minor'] ?? null),
            issuedAt: self::date($payload['issued_at'] ?? null),
            periodStart: self::date($payload['period_start'] ?? null),
            periodEnd: self::date($payload['period_end'] ?? null),
            meterReading: self::float($payload['meter_reading'] ?? null),
            previousMeterReading: self::float($payload['previous_meter_reading'] ?? null),
            consumption: self::float($payload['consumption'] ?? null),
            consumptionUnit: self::string($payload['consumption_unit'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function confidenceFrom(array $payload): float
    {
        $confidence = $payload['confidence'] ?? null;

        if (!is_numeric($confidence)) {
            return 0.0;
        }

        return round(max(0.0, min(1.0, (float) $confidence)), 4);
    }

    private static function string(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            // A model can emit "not a date" despite the schema. A null date is a
            // finding for the gate to weigh, not a reason to lose the extraction.
            return null;
        }
    }
}
