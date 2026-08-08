<?php

declare(strict_types=1);

namespace Modules\Ai\Contracts;

use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\ModelDescriptor;
use Modules\Ai\ValueObjects\SettingField;
use Modules\Ai\ValueObjects\VerificationResult;

/**
 * Stateless description of one vendor: what it can do, what it must be
 * configured with, and how to build a client for a resolved credential.
 */
interface AiProvider
{
    public function id(): string;

    public function label(): string;

    /**
     * @return list<ModelDescriptor>
     */
    public function models(): array;

    public function model(string $id): ?ModelDescriptor;

    /**
     * @return list<SettingField>
     */
    public function settingsSchema(): array;

    /**
     * A cheap round-trip proving the key works for the given model.
     * Returns a failed result for a bad key; it does not throw.
     */
    public function verify(string $apiKey, string $model): VerificationResult;

    public function client(AiConnection $connection): AiClient;
}
