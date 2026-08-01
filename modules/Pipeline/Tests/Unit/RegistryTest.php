<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Unit;

use Modules\Pipeline\Exceptions\UnknownPipelineException;
use Modules\Pipeline\Exceptions\UnknownStepException;
use Modules\Pipeline\Registries\PipelineRegistry;
use Modules\Pipeline\Registries\StepRegistry;
use Modules\Pipeline\Tests\Support\Pipelines\FakeLinearPipeline;
use Modules\Pipeline\Tests\Support\RegistersFakePipelines;
use Modules\Pipeline\Tests\Support\Steps\FakeSuccessStep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegistryTest extends TestCase
{
    use RegistersFakePipelines;

    #[Test]
    public function it_resolves_a_registered_step_by_key(): void
    {
        $registry = app(StepRegistry::class);
        $registry->register(FakeSuccessStep::class);

        $this->assertTrue($registry->has('fake_success'));
        $this->assertInstanceOf(FakeSuccessStep::class, $registry->resolve('fake_success'));
        $this->assertSame(FakeSuccessStep::class, $registry->classFor('fake_success'));
    }

    #[Test]
    public function it_throws_for_an_unknown_step_key(): void
    {
        $this->expectException(UnknownStepException::class);

        app(StepRegistry::class)->resolve('nope');
    }

    #[Test]
    public function it_resolves_a_registered_pipeline_definition(): void
    {
        $registry = app(PipelineRegistry::class);
        $registry->register(new FakeLinearPipeline);

        $definition = $registry->get('fake_linear');

        $this->assertSame(1, $definition->version());
        $this->assertSame(['alpha', 'beta', 'gamma'], $definition->stages());
        $this->assertCount(3, $definition->steps());
    }

    #[Test]
    public function it_throws_for_an_unknown_pipeline_key(): void
    {
        $this->expectException(UnknownPipelineException::class);

        app(PipelineRegistry::class)->get('nope');
    }

    #[Test]
    public function the_registries_are_singletons(): void
    {
        $this->assertSame(app(StepRegistry::class), app(StepRegistry::class));
        $this->assertSame(app(PipelineRegistry::class), app(PipelineRegistry::class));
    }

    #[Test]
    public function the_helper_trait_registers_every_fake(): void
    {
        $this->registerFakePipelines();

        $steps = app(StepRegistry::class);

        foreach (['fake_success', 'fake_failing', 'fake_skipping', 'fake_gate', 'fake_expanding', 'fake_expanded'] as $key) {
            $this->assertTrue($steps->has($key), "step [{$key}] should be registered");
        }

        $this->assertTrue(app(PipelineRegistry::class)->has('fake_linear'));
        $this->assertTrue(app(PipelineRegistry::class)->has('fake_expandable'));
    }
}
