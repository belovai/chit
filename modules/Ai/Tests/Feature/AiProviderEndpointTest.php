<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AiProviderEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_the_anthropic_catalogue(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/ai/providers');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', 'anthropic');
        $response->assertJsonPath('data.0.label', 'Anthropic');
        $response->assertJsonPath('data.0.models.0.id', 'claude-opus-5');
        $response->assertJsonPath('data.0.models.0.pricing.input', 5.0);
        $response->assertJsonPath('data.0.models.0.capabilities.0', 'vision');
    }

    #[Test]
    public function the_settings_descriptor_carries_everything_a_form_needs(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/ai/providers');

        $response->assertJsonPath('data.0.settings.0.key', 'max_tokens');
        $response->assertJsonPath('data.0.settings.0.type', 'int');
        $response->assertJsonPath('data.0.settings.0.min', 1);
        $response->assertJsonPath('data.0.settings.0.max', 64000);
        $response->assertJsonPath('data.0.settings.0.default', 8000);
        $response->assertJsonPath('data.0.settings.1.key', 'effort');
        $response->assertJsonPath('data.0.settings.1.type', 'enum');
        $response->assertJsonPath('data.0.settings.1.options.0', 'low');
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/ai/providers')->assertUnauthorized();
    }
}
