<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Modules\Ai\Enums\Capability;
use Modules\Ai\Exceptions\AiException;
use Modules\Ai\Providers\Anthropic\AnthropicProvider;
use Modules\Ai\ValueObjects\AiConnection;
use Modules\Ai\ValueObjects\AiRequest;
use Modules\Ai\ValueObjects\TextPart;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnthropicProviderTest extends TestCase
{
    private function connection(string $model = 'claude-opus-5'): AiConnection
    {
        return new AiConnection(
            provider: 'anthropic',
            model: $model,
            apiKey: 'sk-ant-test',
            settings: ['max_tokens' => 4000, 'effort' => 'low'],
        );
    }

    #[Test]
    public function it_prices_opus_from_its_descriptor(): void
    {
        $model = app(AnthropicProvider::class)->model('claude-opus-5');

        $this->assertNotNull($model);
        $this->assertSame(5.00, $model->pricing->inputPerMillion);
        $this->assertSame(25.00, $model->pricing->outputPerMillion);
        $this->assertSame(0.50, $model->pricing->cachedInputPerMillion);
        $this->assertTrue($model->supports(Capability::Vision));
    }

    #[Test]
    public function an_empty_api_key_is_rejected_before_any_network_call(): void
    {
        $connection = new AiConnection('anthropic', 'claude-opus-5', apiKey: '');

        $this->expectException(AiException::class);
        $this->expectExceptionMessage('No API key is configured for this connection.');

        app(AnthropicProvider::class)->client($connection)
            ->complete(new AiRequest('system', [new TextPart('hi')]));
    }

    #[Test]
    public function an_unknown_model_fails_permanently_before_any_network_call(): void
    {
        $connection = new AiConnection('anthropic', 'claude-from-2030', 'sk-ant-test');

        $this->expectException(AiException::class);
        $this->expectExceptionMessage('Unknown model [claude-from-2030].');

        app(AnthropicProvider::class)->client($connection)
            ->complete(new AiRequest('system', [new TextPart('hi')]));
    }
}
