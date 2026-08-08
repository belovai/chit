<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Support;

use Modules\Ai\Contracts\AiProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

abstract class AiProviderContractTestCase extends TestCase
{
    abstract protected function provider(): AiProvider;

    #[Test]
    public function it_declares_a_non_empty_id_and_label(): void
    {
        $this->assertNotSame('', $this->provider()->id());
        $this->assertNotSame('', $this->provider()->label());
    }

    #[Test]
    public function it_offers_at_least_one_model(): void
    {
        $this->assertNotEmpty($this->provider()->models());
    }

    #[Test]
    public function every_model_id_is_unique(): void
    {
        $ids = array_map(fn ($model): string => $model->id, $this->provider()->models());

        $this->assertSame($ids, array_values(array_unique($ids)));
    }

    #[Test]
    public function every_declared_model_is_findable_by_id(): void
    {
        foreach ($this->provider()->models() as $model) {
            $this->assertNotNull($this->provider()->model($model->id));
        }
    }

    #[Test]
    public function an_unknown_model_id_resolves_to_null(): void
    {
        $this->assertNull($this->provider()->model('definitely-not-a-model'));
    }

    #[Test]
    public function every_setting_field_accepts_its_own_default(): void
    {
        foreach ($this->provider()->settingsSchema() as $field) {
            $this->assertNull(
                $field->validate($field->default),
                'Default for ['.$field->key.'] fails its own validation.',
            );
        }
    }

    #[Test]
    public function verification_of_an_unknown_model_fails_without_throwing(): void
    {
        $result = $this->provider()->verify('sk-irrelevant', 'definitely-not-a-model');

        $this->assertFalse($result->ok);
        $this->assertNotNull($result->message);
    }
}
