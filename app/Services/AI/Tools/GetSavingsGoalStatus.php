<?php

namespace App\Services\AI\Tools;

use App\Services\AI\FinancialTool;
use App\Services\SavingsGoalService;
use Carbon\CarbonImmutable;

/**
 * Status of every active savings goal from the deterministic goal engine,
 * including progress, remaining amount and required saving per period.
 */
class GetSavingsGoalStatus implements FinancialTool
{
    public function __construct(private SavingsGoalService $goals)
    {
    }

    public function name(): string
    {
        return 'getSavingsGoalStatus';
    }

    public function description(): string
    {
        return 'Get the status of every active savings goal: target, saved, remaining, progress percent and how much the user must save daily/weekly/monthly to meet the deadline.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function run(int $userId, ?CarbonImmutable $reference, array $arguments = []): array
    {
        $goals = \App\Models\SavingsGoal::query()
            ->where('user_id', $userId)
            ->where('is_completed', false)
            ->get()
            ->map(function ($g) use ($reference) {
                $s = $this->goals->status($g, $reference);

                return [
                    'name' => $g->name,
                    'target' => (float) $g->target_amount,
                    'saved' => (float) $s['saved'],
                    'remaining' => (float) $s['remaining'],
                    'progress_percent' => $s['progress_percent'],
                    'deadline' => $g->deadline?->toDateString(),
                    'days_remaining' => $s['days_remaining'],
                    'required_daily' => $s['required_daily'] !== null ? (float) $s['required_daily'] : null,
                    'required_weekly' => $s['required_weekly'] !== null ? (float) $s['required_weekly'] : null,
                    'required_monthly' => $s['required_monthly'] !== null ? (float) $s['required_monthly'] : null,
                ];
            })
            ->values()
            ->all();

        return ['savings_goals' => $goals];
    }
}
