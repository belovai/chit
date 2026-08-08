<?php

declare(strict_types=1);

namespace Modules\Ai\Console;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Modules\Ai\Actions\CreateAiCredential;
use Modules\Ai\Contracts\AiProvider;
use Modules\Ai\Registries\ProviderRegistry;
use Modules\User\Models\User;

/**
 * The local-development path. There is no system fallback key, so a fresh
 * checkout needs this before any document can be processed.
 */
final class CreateAiCredentialCommand extends Command
{
    protected $signature = 'ai:credential:create
        {email : the user who owns the key}
        {--provider=anthropic}
        {--model=}
        {--label=Local development}';

    protected $description = 'Store an AI API key for a user, prompting for the key itself';

    public function handle(ProviderRegistry $providers, CreateAiCredential $create): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email address.');

            return self::FAILURE;
        }

        $providerId = (string) $this->option('provider');

        if (!$providers->has($providerId)) {
            $this->error('Unknown provider ['.$providerId.'].');

            return self::FAILURE;
        }

        $provider = $providers->get($providerId);
        $model = (string) ($this->option('model') ?: $provider->models()[0]->id);

        // Prompted, never an argument: a key passed on the command line lands
        // in the shell history and in the process list.
        $key = (string) $this->secret('API key');

        if ($key === '') {
            $this->error('No key entered.');

            return self::FAILURE;
        }

        try {
            $credential = $create->handle($user->id, [
                'provider' => $providerId,
                'label' => (string) $this->option('label'),
                'api_key' => $key,
                'model' => $model,
                'settings' => $this->defaultSettings($provider),
            ]);
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        }

        $this->info('Stored '.$credential->maskedKey().' for '.$user->email.' ('.$model.').');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(AiProvider $provider): array
    {
        $settings = [];

        foreach ($provider->settingsSchema() as $field) {
            $settings[$field->key] = $field->default;
        }

        return $settings;
    }
}
