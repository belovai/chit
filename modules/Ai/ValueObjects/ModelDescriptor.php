<?php

declare(strict_types=1);

namespace Modules\Ai\ValueObjects;

use Modules\Ai\Enums\Capability;

final readonly class ModelDescriptor
{
    /**
     * @param  list<Capability>  $capabilities
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $capabilities,
        public ModelPricing $pricing,
    ) {}

    public function supports(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
