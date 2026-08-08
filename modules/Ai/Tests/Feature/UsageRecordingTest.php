<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Ai\Exceptions\AiException;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Models\AiUsageLog;
use Modules\Ai\Services\AiClientFactory;
use Modules\Ai\Services\AiConnectionResolver;
use Modules\Ai\Testing\FakeAiProvider;
use Modules\Ai\ValueObjects\AiRequest;
use Modules\Ai\ValueObjects\AiUsage;
use Modules\Ai\ValueObjects\TextPart;
use Modules\Ai\ValueObjects\UsageContext;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UsageRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.fake', true);
        FakeAiProvider::reset();
    }

    #[Test]
    public function a_successful_call_writes_one_usage_row(): void
    {
        FakeAiProvider::willRespond(
            ['ok' => true],
            new AiUsage(inputTokens: 1_000, outputTokens: 200, cachedInputTokens: 50, costUsdMicros: 10_000),
        );

        $user = User::factory()->create();
        $credential = AiCredential::factory()->for($user, 'owner')->active()->create();

        $connection = app(AiConnectionResolver::class)->forCredential($credential);

        app(AiClientFactory::class)
            ->for($connection, new UsageContext('extraction.classify'))
            ->complete(new AiRequest('system', [new TextPart('hello')]));

        $log = AiUsageLog::query()->sole();

        $this->assertSame($user->id, $log->owner_id);
        $this->assertSame($credential->id, $log->ai_credential_id);
        $this->assertSame('fake', $log->provider);
        $this->assertSame('fake-model', $log->model);
        $this->assertSame('extraction.classify', $log->purpose);
        $this->assertSame(1_000, $log->input_tokens);
        $this->assertSame(200, $log->output_tokens);
        $this->assertSame(50, $log->cached_input_tokens);
        $this->assertSame(10_000, $log->cost_usd_micros);
    }

    #[Test]
    public function a_failed_call_writes_no_usage_row(): void
    {
        FakeAiProvider::willFail(AiException::retryable('overloaded'));

        $credential = AiCredential::factory()->active()->create();
        $connection = app(AiConnectionResolver::class)->forCredential($credential);

        try {
            app(AiClientFactory::class)->for($connection)
                ->complete(new AiRequest('system', [new TextPart('hello')]));
        } catch (AiException) {
            // expected
        }

        $this->assertSame(0, AiUsageLog::query()->count());
    }

    #[Test]
    public function a_successful_call_touches_last_used_at(): void
    {
        FakeAiProvider::willRespond(['ok' => true]);

        $credential = AiCredential::factory()->active()->create(['last_used_at' => null]);
        $connection = app(AiConnectionResolver::class)->forCredential($credential);

        app(AiClientFactory::class)->for($connection)
            ->complete(new AiRequest('system', [new TextPart('hello')]));

        $this->assertNotNull($credential->fresh()?->last_used_at);
    }

    #[Test]
    public function deleting_a_credential_keeps_its_usage_history(): void
    {
        $credential = AiCredential::factory()->active()->create();
        AiUsageLog::factory()->for($credential, 'credential')->create([
            'owner_id' => $credential->owner_id,
            'provider' => 'fake',
            'model' => 'fake-model',
        ]);

        $credential->delete();

        $log = AiUsageLog::query()->sole();

        $this->assertNull($log->ai_credential_id);
        $this->assertSame('fake', $log->provider, 'the provider is denormalised for exactly this case');
    }
}
