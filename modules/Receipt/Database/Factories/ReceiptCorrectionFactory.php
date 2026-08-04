<?php

declare(strict_types=1);

namespace Modules\Receipt\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Receipt\Models\Receipt;
use Modules\Receipt\Models\ReceiptCorrection;
use Modules\User\Models\User;

/**
 * @extends Factory<ReceiptCorrection>
 */
final class ReceiptCorrectionFactory extends Factory
{
    protected $model = ReceiptCorrection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'receipt_id' => Receipt::factory(),
            'doc_type' => 'receipt',
            'field_path' => 'total_minor',
            'ai_value' => ['value' => 132700],
            'corrected_value' => ['value' => 132800],
            'created_at' => now(),
        ];
    }
}
