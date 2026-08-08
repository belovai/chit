<?php

declare(strict_types=1);

namespace Modules\Ai\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Models\AiUsageLog;
use Modules\User\Actions\DeleteAccount;
use Modules\User\Jobs\PurgeUserData;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AiUsageEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_totals_the_users_spend_in_the_window(): void
    {
        $user = User::factory()->create();

        AiUsageLog::factory()->for($user, 'owner')->create([
            'model' => 'fake-model',
            'input_tokens' => 1_000,
            'output_tokens' => 200,
            'cost_usd_micros' => 10_000,
            'created_at' => now()->subDay(),
        ]);
        AiUsageLog::factory()->for($user, 'owner')->create([
            'model' => 'fake-model',
            'input_tokens' => 500,
            'output_tokens' => 100,
            'cost_usd_micros' => 5_000,
            'created_at' => now()->subDays(2),
        ]);
        // Outside the window.
        AiUsageLog::factory()->for($user, 'owner')->create([
            'cost_usd_micros' => 999_999,
            'created_at' => now()->subMonths(2),
        ]);

        $response = $this->withToken($this->tokenFor($user))->getJson(
            '/api/ai/usage?from='.now()->subWeek()->toDateString().'&to='.now()->toDateString(),
        );

        $response->assertOk();
        $response->assertJsonPath('data.totals.cost_usd_micros', 15_000);
        $response->assertJsonPath('data.totals.input_tokens', 1_500);
        $response->assertJsonPath('data.by_model.0.model', 'fake-model');
        $response->assertJsonPath('data.by_model.0.calls', 2);
    }

    #[Test]
    public function another_users_spend_is_never_included(): void
    {
        $ada = User::factory()->create();
        AiUsageLog::factory()->for($ada, 'owner')->create(['cost_usd_micros' => 1_000]);
        AiUsageLog::factory()->for(User::factory(), 'owner')->create(['cost_usd_micros' => 500_000]);

        $response = $this->withToken($this->tokenFor($ada))->getJson('/api/ai/usage');

        $response->assertJsonPath('data.totals.cost_usd_micros', 1_000);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/ai/usage')->assertUnauthorized();
    }

    #[Test]
    public function requesting_account_deletion_revokes_the_keys_immediately(): void
    {
        // The queue is `sync` under test, so the purge would otherwise run inside
        // handle() and this test could not see the intermediate state at all.
        Queue::fake();

        $user = User::factory()->create();
        AiCredential::factory()->for($user, 'owner')->active()->create();
        AiUsageLog::factory()->for($user, 'owner')->create();

        app(DeleteAccount::class)->handle($user);

        $this->assertSame(0, AiCredential::query()->count(), 'keys go at once');
        $this->assertSame(1, AiUsageLog::query()->count(), 'history survives until the purge');
        $this->assertNull(AiUsageLog::query()->sole()->ai_credential_id);

        Queue::assertPushed(PurgeUserData::class);
    }

    #[Test]
    public function the_purge_removes_the_remaining_usage_history(): void
    {
        $user = User::factory()->create();
        AiUsageLog::factory()->for($user, 'owner')->create();

        // Not faked here: the sync queue runs PurgeUserData as part of handle(),
        // which is exactly the end state this test is about. Do not call the job
        // a second time — the user is already force-deleted by then.
        app(DeleteAccount::class)->handle($user);

        $this->assertSame(0, AiUsageLog::query()->count());
    }
}
