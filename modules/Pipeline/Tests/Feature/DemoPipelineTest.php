<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pipeline\Actions\StartRun;
use Modules\Pipeline\Enums\RunStatus;
use Modules\Pipeline\Enums\StepStatus;
use Modules\Pipeline\Models\PipelineRun;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DemoPipelineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_demo_run_parks_on_its_gate_and_shows_every_visual_state(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('demo', $user->id)->refresh();
        $steps = $run->currentSteps()->keyBy('step_key');

        $this->assertSame(RunStatus::AwaitingManual, $run->status);
        $this->assertSame(StepStatus::Succeeded, $steps['demo_ingest']->status);
        $this->assertSame(StepStatus::Failed, $steps['demo_read']->status, 'allowFailure step, drives the warning colour');
        $this->assertTrue($steps['demo_read']->allow_failure);
        $this->assertSame(StepStatus::AwaitingManual, $steps['demo_gate']->status);
        $this->assertSame(StepStatus::Pending, $steps['demo_commit']->status);
        $this->assertArrayHasKey('demo_extract', $steps, 'added by the classify step');
        $this->assertNotNull($steps['demo_extract']->added_by_step_id);
    }

    #[Test]
    public function the_demo_run_records_cost_and_confidence(): void
    {
        $user = User::factory()->create();

        $run = app(StartRun::class)->handle('demo', $user->id)->refresh();
        $extract = $run->currentSteps()->firstWhere('step_key', 'demo_extract');

        $this->assertSame(0.82, $extract?->confidence);
        $this->assertSame(12400, $extract?->cost_usd_micros);
        $this->assertSame('low_ocr_confidence', $extract?->findings[0]['code']);
    }

    #[Test]
    public function the_command_starts_a_run_for_the_named_user(): void
    {
        $user = User::factory()->create(['email' => 'demo@example.com']);

        $this->artisan('pipeline:demo --user=demo@example.com')->assertSuccessful();

        $this->assertSame(1, PipelineRun::query()->where('owner_id', $user->id)->count());
    }

    #[Test]
    public function the_pass_flag_produces_a_run_that_finishes_on_its_own(): void
    {
        $user = User::factory()->create(['email' => 'demo@example.com']);

        $this->artisan('pipeline:demo --user=demo@example.com --pass')->assertSuccessful();

        $run = PipelineRun::query()->where('owner_id', $user->id)->firstOrFail();
        $this->assertSame(RunStatus::Warning, $run->status);
    }
}
