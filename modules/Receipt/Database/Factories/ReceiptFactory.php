<?php

declare(strict_types=1);

namespace Modules\Receipt\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Receipt\Enums\ReceiptStatus;
use Modules\Receipt\Models\Receipt;
use Modules\User\Models\User;

/**
 * @extends Factory<Receipt>
 */
final class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'original_filename' => 'receipt.jpg',
            'disk' => 'local',
            'path' => 'receipts/'.fake()->uuid().'.jpg',
            'file_hash' => hash('sha256', fake()->unique()->uuid()),
            'mime' => 'image/jpeg',
            'size_bytes' => 184320,
            'status' => ReceiptStatus::Pending,
        ];
    }
}
