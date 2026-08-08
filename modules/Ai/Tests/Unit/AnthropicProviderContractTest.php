<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Unit;

use Modules\Ai\Contracts\AiProvider;
use Modules\Ai\Providers\Anthropic\AnthropicProvider;
use Modules\Ai\Tests\Support\AiProviderContractTestCase;

final class AnthropicProviderContractTest extends AiProviderContractTestCase
{
    protected function provider(): AiProvider
    {
        return app(AnthropicProvider::class);
    }
}
