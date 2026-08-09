<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Unit;

use Modules\Ai\Enums\Capability;
use Modules\Ai\ValueObjects\ModelDescriptor;
use Modules\Ai\ValueObjects\ModelPricing;
use Modules\Ai\ValueObjects\SettingField;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DescriptorTest extends TestCase
{
    #[Test]
    public function a_model_reports_the_capabilities_it_declares(): void
    {
        $model = new ModelDescriptor(
            id: 'claude-opus-5',
            label: 'Claude Opus 5',
            capabilities: [Capability::Vision, Capability::JsonSchema],
            pricing: new ModelPricing(5.00, 25.00, 0.50),
        );

        $this->assertTrue($model->supports(Capability::Vision));
        $this->assertFalse($model->supports(Capability::PromptCache));
    }

    #[Test]
    public function an_int_field_rejects_values_outside_its_range(): void
    {
        $field = SettingField::int('max_tokens', default: 8000, min: 1, max: 64000);

        $this->assertNull($field->validate(8000));
        $this->assertSame('ai.setting_above_max', $field->validate(70000));
        $this->assertSame('ai.setting_below_min', $field->validate(0));
        $this->assertSame('ai.setting_not_an_integer', $field->validate('lots'));
    }

    #[Test]
    public function an_enum_field_rejects_options_it_does_not_list(): void
    {
        $field = SettingField::enum('effort', default: 'low', options: ['low', 'high']);

        $this->assertNull($field->validate('high'));
        $this->assertSame('ai.setting_not_an_option', $field->validate('extreme'));
    }

    #[Test]
    public function a_bool_field_accepts_only_booleans(): void
    {
        $field = SettingField::bool('stream', default: false);

        $this->assertNull($field->validate(true));
        $this->assertSame('ai.setting_not_a_boolean', $field->validate('yes'));
    }
}
