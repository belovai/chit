<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Unit;

use Modules\Ai\Exceptions\UnknownAiProviderException;
use Modules\Ai\Registries\ProviderRegistry;
use Modules\Ai\Testing\FakeAiProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProviderRegistryTest extends TestCase
{
    #[Test]
    public function it_returns_a_registered_provider_by_id(): void
    {
        $registry = new ProviderRegistry;
        $registry->register(new FakeAiProvider);

        $this->assertTrue($registry->has('fake'));
        $this->assertSame('fake', $registry->get('fake')->id());
        $this->assertCount(1, $registry->all());
    }

    #[Test]
    public function an_unknown_id_throws_rather_than_returning_null(): void
    {
        $this->expectException(UnknownAiProviderException::class);
        $this->expectExceptionMessage('Unknown AI provider [nope].');

        (new ProviderRegistry)->get('nope');
    }

    #[Test]
    public function registering_the_same_id_twice_replaces_the_first(): void
    {
        $registry = new ProviderRegistry;
        $registry->register(new FakeAiProvider);
        $registry->register(new FakeAiProvider);

        $this->assertCount(1, $registry->all());
    }
}
