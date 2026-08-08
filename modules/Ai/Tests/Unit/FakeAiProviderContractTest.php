<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Unit;

use Modules\Ai\Contracts\AiProvider;
use Modules\Ai\Testing\FakeAiProvider;
use Modules\Ai\Tests\Support\AiProviderContractTestCase;

final class FakeAiProviderContractTest extends AiProviderContractTestCase
{
    protected function provider(): AiProvider
    {
        return new FakeAiProvider;
    }
}
