<?php

declare(strict_types=1);

namespace Modules\Pipeline\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Pipeline\Enums\ArtifactKind;
use Modules\Pipeline\Models\PipelineArtifact;
use Modules\Pipeline\Models\PipelineRun;
use Modules\Pipeline\Models\PipelineRunStep;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PipelineRunArtifactTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_a_text_artifact_payload(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $step = PipelineRunStep::factory()->for($run, 'run')->create();
        PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'ocr_text',
            'kind' => ArtifactKind::Text,
            'payload' => ['text' => 'ALDI 1234'],
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}/artifacts/ocr_text");

        $response->assertOk();
        $response->assertJsonPath('data.key', 'ocr_text');
        $response->assertJsonPath('data.kind', 'text');
        $response->assertJsonPath('data.payload.text', 'ALDI 1234');
    }

    #[Test]
    public function it_returns_the_live_artifact_not_a_superseded_one(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $step = PipelineRunStep::factory()->for($run, 'run')->create();
        PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'doc_type', 'payload' => ['value' => 'old'], 'superseded_at' => now(),
        ]);
        PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'doc_type', 'payload' => ['value' => 'new'],
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}/artifacts/doc_type")
            ->assertJsonPath('data.payload.value', 'new');
    }

    #[Test]
    public function it_streams_a_binary_artifact(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('runs/x/image.png', 'binary-bytes');

        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $step = PipelineRunStep::factory()->for($run, 'run')->create();
        PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'normalized_image',
            'kind' => ArtifactKind::Binary,
            'payload' => null,
            'disk' => 'local',
            'path' => 'runs/x/image.png',
            'mime' => 'image/png',
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/pipeline-runs/{$run->hash_id}/artifacts/normalized_image");

        $response->assertOk();
        $this->assertSame('binary-bytes', $response->streamedContent());
    }

    #[Test]
    public function a_pruned_binary_artifact_is_gone(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $step = PipelineRunStep::factory()->for($run, 'run')->create();
        PipelineArtifact::factory()->for($run, 'run')->for($step, 'step')->create([
            'key' => 'normalized_image',
            'kind' => ArtifactKind::Binary,
            'payload' => null,
            'disk' => 'local',
            'path' => null,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}/artifacts/normalized_image")
            ->assertNotFound();
    }

    #[Test]
    public function an_unknown_artifact_key_is_a_404(): void
    {
        $user = User::factory()->create();
        $run = PipelineRun::factory()->for($user, 'owner')->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/pipeline-runs/{$run->hash_id}/artifacts/ghost")
            ->assertNotFound();
    }
}
