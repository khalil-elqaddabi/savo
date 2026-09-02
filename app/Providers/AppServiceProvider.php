<?php

namespace App\Providers;

use App\Services\AI\AIServiceInterface;
use App\Services\AI\AssistantService;
use App\Services\AI\FinancialToolRegistry;
use App\Services\AI\NullAIProvider;
use App\Services\AI\OpenAIService;
use App\Services\AI\Tools\CompareMonths;
use App\Services\AI\Tools\GetAccountBalances;
use App\Services\AI\Tools\GetAffordability;
use App\Services\AI\Tools\GetBills;
use App\Services\AI\Tools\GetBudgetStatus;
use App\Services\AI\Tools\GetCategorySpending;
use App\Services\AI\Tools\GetDebts;
use App\Services\AI\Tools\GetFinancialSummary;
use App\Services\AI\Tools\GetForecast;
use App\Services\AI\Tools\GetRecentTransactions;
use App\Services\AI\Tools\GetRecurringUpcoming;
use App\Services\AI\Tools\GetSafeToSpend;
use App\Services\AI\Tools\GetSavingsGoalStatus;
use App\Services\LocaleService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocaleService::class);
        $this->app->alias(LocaleService::class, 'savo.locale');

        $this->app->singleton(FinancialToolRegistry::class, function ($app) {
            return new FinancialToolRegistry([
                $app->make(GetFinancialSummary::class),
                $app->make(GetAccountBalances::class),
                $app->make(GetCategorySpending::class),
                $app->make(GetBudgetStatus::class),
                $app->make(GetSavingsGoalStatus::class),
                $app->make(GetRecurringUpcoming::class),
                $app->make(GetForecast::class),
                $app->make(GetSafeToSpend::class),
                $app->make(CompareMonths::class),
                $app->make(GetAffordability::class),
                $app->make(GetRecentTransactions::class),
                $app->make(GetBills::class),
                $app->make(GetDebts::class),
            ]);
        });

        $this->app->bind(AIServiceInterface::class, function ($app) {
            if (blank(config('services.ai.api_key'))) {
                return new NullAIProvider();
            }

            // Only OpenAI / OpenAI-compatible chat-completions transports are
            // implemented today. `openai-compatible` reuses the same transport
            // through AI_BASE_URL / AI_MODEL. Any unknown provider value
            // degrades to the same transport rather than pretending to be
            // something that is not implemented.
            $provider = config('services.ai.provider', 'openai');

            return match ($provider) {
                'openai', 'openai-compatible' => new OpenAIService(),
                default => new OpenAIService(),
            };
        });

        $this->app->singleton(AssistantService::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
