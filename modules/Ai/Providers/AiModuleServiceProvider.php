<?php

declare(strict_types=1);

namespace Modules\Ai\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Ai\Console\CreateAiCredentialCommand;
use Modules\Ai\Listeners\RevokeAiCredentials;
use Modules\Ai\Models\AiCredential;
use Modules\Ai\Policies\AiCredentialPolicy;
use Modules\Ai\Providers\Anthropic\AnthropicProvider;
use Modules\Ai\Registries\ProviderRegistry;
use Modules\Ai\Services\CostCalculator;
use Modules\Ai\Testing\FakeAiProvider;
use Modules\User\Events\AccountDeleted;

final class AiModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/ai.php', 'ai');

        $this->app->singleton(CostCalculator::class);

        $this->app->singleton(ProviderRegistry::class, function (): ProviderRegistry {
            $registry = new ProviderRegistry;

            // CostCalculator depends on ProviderRegistry, and AnthropicProvider
            // depends on CostCalculator: bind the instance now so that nested
            // resolution below finds it instead of re-entering this closure.
            $this->app->instance(ProviderRegistry::class, $registry);

            if ((bool) config('ai.fake')) {
                $registry->register(new FakeAiProvider);

                return $registry;
            }

            $registry->register($this->app->make(AnthropicProvider::class));

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        Route::model('credential', AiCredential::class);
        Gate::policy(AiCredential::class, AiCredentialPolicy::class);

        Event::listen(AccountDeleted::class, RevokeAiCredentials::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAiCredentialCommand::class]);
        }
    }
}
