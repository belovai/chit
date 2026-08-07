<?php

declare(strict_types=1);

namespace Modules\Extraction\Tests\Unit;

use Modules\Extraction\Ai\Support\DocumentMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DocumentMapperTest extends TestCase
{
    #[Test]
    public function it_maps_a_receipt_payload(): void
    {
        $receipt = DocumentMapper::toReceipt([
            'merchant_name' => 'ALDI',
            'occurred_at' => '2026-07-30T14:12:00',
            'currency' => 'HUF',
            'total_minor' => 132700,
            'discount_minor' => null,
            'payment_method' => 'card',
            'items' => [
                ['description' => 'Tej 2.8%', 'quantity' => 2, 'unit' => 'db', 'unit_price_minor' => 38900, 'total_minor' => 77800],
                ['description' => 'Kenyer', 'quantity' => 1, 'unit' => 'db', 'unit_price_minor' => 54900, 'total_minor' => 54900],
            ],
        ]);

        $this->assertSame('ALDI', $receipt->merchantName);
        $this->assertSame('HUF', $receipt->currency);
        $this->assertSame(132700, $receipt->totalMinor);
        $this->assertSame('card', $receipt->paymentMethod);
        $this->assertCount(2, $receipt->items);
        $this->assertSame('Tej 2.8%', $receipt->items[0]->description);
        $this->assertSame(2.0, $receipt->items[0]->quantity);
        $this->assertSame('2026-07-30 14:12', $receipt->occurredAt?->format('Y-m-d H:i'));
    }

    #[Test]
    public function a_receipt_payload_with_every_field_null_maps_without_throwing(): void
    {
        $receipt = DocumentMapper::toReceipt([
            'merchant_name' => null,
            'occurred_at' => null,
            'currency' => null,
            'total_minor' => null,
            'discount_minor' => null,
            'payment_method' => null,
            'items' => [],
        ]);

        $this->assertNull($receipt->merchantName);
        $this->assertNull($receipt->occurredAt);
        $this->assertSame([], $receipt->items);
        $this->assertSame(0, $receipt->itemsTotalMinor());
    }

    #[Test]
    public function an_unparseable_date_becomes_null_rather_than_throwing(): void
    {
        $receipt = DocumentMapper::toReceipt(['occurred_at' => 'not a date', 'items' => []]);

        $this->assertNull($receipt->occurredAt);
    }

    #[Test]
    public function a_missing_items_key_maps_to_an_empty_list(): void
    {
        $receipt = DocumentMapper::toReceipt(['merchant_name' => 'ALDI']);

        $this->assertSame([], $receipt->items);
    }

    #[Test]
    public function it_maps_the_branch_address(): void
    {
        $receipt = DocumentMapper::toReceipt([
            'merchant_name' => 'SPAR',
            'merchant_address' => '6723 Szeged, Szilléri sugár út 26.',
            'items' => [],
        ]);

        $this->assertSame('SPAR', $receipt->merchantName);
        $this->assertSame('6723 Szeged, Szilléri sugár út 26.', $receipt->merchantAddress);
    }

    #[Test]
    public function a_missing_branch_address_maps_to_null(): void
    {
        $receipt = DocumentMapper::toReceipt(['merchant_name' => 'SPAR', 'items' => []]);

        $this->assertNull($receipt->merchantAddress);
    }

    #[Test]
    public function it_maps_a_utility_bill_payload(): void
    {
        $bill = DocumentMapper::toBill([
            'provider_name' => 'ELMU',
            'customer_reference' => '1234567890',
            'currency' => 'HUF',
            'total_minor' => 1845000,
            'issued_at' => '2026-07-05',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'meter_reading' => 45231,
            'previous_meter_reading' => 44919,
            'consumption' => 312,
            'consumption_unit' => 'kWh',
        ]);

        $this->assertSame('ELMU', $bill->providerName);
        $this->assertSame('1234567890', $bill->customerReference);
        $this->assertSame(45231.0, $bill->meterReading);
        $this->assertSame(312.0, $bill->consumption);
        $this->assertSame('kWh', $bill->consumptionUnit);
        $this->assertSame('2026-06-30', $bill->periodEnd?->format('Y-m-d'));
    }

    #[Test]
    public function confidence_is_clamped_into_zero_to_one(): void
    {
        $this->assertSame(1.0, DocumentMapper::confidenceFrom(['confidence' => 4.2]));
        $this->assertSame(0.0, DocumentMapper::confidenceFrom(['confidence' => -1]));
        $this->assertSame(0.0, DocumentMapper::confidenceFrom([]));
        $this->assertSame(0.87, DocumentMapper::confidenceFrom(['confidence' => 0.87]));
    }
}
