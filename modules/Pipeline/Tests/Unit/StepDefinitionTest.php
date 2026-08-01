<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Unit;

use Modules\Pipeline\Tests\Support\Steps\FakeSuccessStep;
use Modules\Pipeline\ValueObjects\StepDefinition;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StepDefinitionTest extends TestCase
{
    #[Test]
    public function it_derives_the_key_from_the_step_class(): void
    {
        $definition = StepDefinition::make(FakeSuccessStep::class)->inStage('alpha');

        $this->assertSame('fake_success', $definition->key());
        $this->assertSame(FakeSuccessStep::class, $definition->stepClass());
        $this->assertSame('alpha', $definition->stage());
    }

    #[Test]
    public function it_defaults_to_no_dependencies_one_attempt_not_gate_not_allow_failure(): void
    {
        $definition = StepDefinition::make(FakeSuccessStep::class)->inStage('alpha');

        $this->assertSame([], $definition->dependencies());
        $this->assertSame(1, $definition->attempts());
        $this->assertFalse($definition->isGate());
        $this->assertFalse($definition->isAllowFailure());
        $this->assertSame([], $definition->config());
    }

    #[Test]
    public function the_builder_methods_are_chainable(): void
    {
        $definition = StepDefinition::make(FakeSuccessStep::class)
            ->inStage('beta')
            ->dependsOn('store_file', 'ocr')
            ->allowFailure()
            ->asGate()
            ->maxAttempts(3)
            ->withConfig(['threshold' => 0.8]);

        $this->assertSame('beta', $definition->stage());
        $this->assertSame(['store_file', 'ocr'], $definition->dependencies());
        $this->assertTrue($definition->isAllowFailure());
        $this->assertTrue($definition->isGate());
        $this->assertSame(3, $definition->attempts());
        $this->assertSame(['threshold' => 0.8], $definition->config());
    }
}
