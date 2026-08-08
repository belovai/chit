<?php

declare(strict_types=1);

namespace Modules\Ai\Controllers;

use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Ai\Actions\ActivateAiCredential;
use Modules\Ai\Actions\CreateAiCredential;
use Modules\Ai\Actions\DeleteAiCredential;
use Modules\Ai\Actions\UpdateAiCredential;
use Modules\Ai\Actions\VerifyAiCredential;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Requests\StoreAiCredentialRequest;
use Modules\Ai\Requests\UpdateAiCredentialRequest;
use Modules\Ai\Resources\AiCredentialResource;

final class AiCredentialController
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        return $this->ok(
            data: AiCredentialResource::collection(
                AiCredential::query()
                    ->forUser((int) $request->user()?->getAuthIdentifier())
                    ->orderByDesc('is_active')
                    ->orderBy('label')
                    ->get(),
            )->resolve(),
        );
    }

    public function store(StoreAiCredentialRequest $request, CreateAiCredential $create): JsonResponse
    {
        /** @var array{provider: string, label: string, api_key: string, model: string, settings: array<string, mixed>} $data */
        $data = $request->validated();

        return $this->created(
            data: AiCredentialResource::make(
                $create->handle((int) $request->user()?->getAuthIdentifier(), $data),
            ),
        );
    }

    public function update(
        UpdateAiCredentialRequest $request,
        AiCredential $credential,
        UpdateAiCredential $update,
    ): JsonResponse {
        return $this->ok(
            data: AiCredentialResource::make($update->handle($credential, $request->validated())),
        );
    }

    public function activate(Request $request, AiCredential $credential, ActivateAiCredential $activate): JsonResponse
    {
        return $this->ok(data: AiCredentialResource::make($activate->handle($credential)));
    }

    public function verify(Request $request, AiCredential $credential, VerifyAiCredential $verify): JsonResponse
    {
        return $this->ok(data: AiCredentialResource::make($verify->handle($credential)));
    }

    public function destroy(Request $request, AiCredential $credential, DeleteAiCredential $delete): JsonResponse
    {
        $delete->handle($credential);

        return $this->ok(message: 'deleted');
    }
}
