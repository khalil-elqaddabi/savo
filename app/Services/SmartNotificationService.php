<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Notification;
use App\Models\SavingsGoal;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates meaningful, low-noise in-app notifications.
 *
 * Rules:
 *  - Every notification is scoped to the authenticated user.
 *  - The same notification kind + related entity is emitted at most once per
 *    day (deduplication) so recurring alerts never become spam.
 *  - A generated notification is marked read automatically when the user
 *    acknowledges it via the read route.
 */
class SmartNotificationService
{
    public const KIND_BUDGET_ALERT = 'budget_alert';
    public const KIND_UPCOMING_BILL = 'upcoming_bill';
    public const KIND_GOAL_PROGRESS = 'goal_progress';
    public const KIND_UNUSUAL_SPENDING = 'unusual_spending';

    public function generate(int $userId, ?CarbonImmutable $reference = null): void
    {
        $reference ??= CarbonImmutable::now();

        $this->budgetAlerts($userId, $reference);
        $this->upcomingBills($userId, $reference);
        $this->goalProgress($userId, $reference);
        $this->unusualSpending($userId, $reference);
    }

    public function create(int $userId, string $kind, string $title, string $message, ?object $related = null, ?array $data = null): ?Notification
    {
        $relatedType = $related ? $related->getMorphClass() : null;
        $relatedId = $related ? $related->getKey() : null;

        $existsInDay = Notification::query()
            ->forUser($userId)
            ->ofKind($kind)
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfDay()->toDateTimeString())
            ->exists();

        if ($existsInDay) {
            return null;
        }

        return Notification::query()->create([
            'id' => (string) Str::uuid(),
            'notifiable_id' => $userId,
            'notifiable_type' => \App\Models\User::class,
            'type' => \App\Notifications\SmartNotification::class,
            'kind' => $kind,
            'title' => $title,
            'message' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'data' => $data,
        ]);
    }

    public function markAsRead(int $userId, string $notificationId): void
    {
        Notification::query()
            ->forUser($userId)
            ->whereKey($notificationId)
            ->update(['read_at' => CarbonImmutable::now()]);
    }

    public function markAllAsRead(int $userId): void
    {
        Notification::query()
            ->forUser($userId)
            ->whereNull('read_at')
            ->update(['read_at' => CarbonImmutable::now()]);
    }

    private function budgetAlerts(int $userId, CarbonImmutable $reference): void
    {
        $budgets = Budget::query()->where('user_id', $userId)->where('is_active', true)->get();

        foreach ($budgets as $budget) {
            $status = app(BudgetService::class)->status($budget, $reference);
            $percent = (int) $status['percent'];

            if ($percent >= 85 && $percent < 100) {
                $this->create(
                    $userId,
                    self::KIND_BUDGET_ALERT,
                    __('Smart notification.budget_alert_title', ['name' => $budget->name]),
                    __('Smart notification.budget_alert', ['name' => $budget->name, 'percent' => $percent]),
                    $budget,
                    ['percent' => $percent, 'spent' => $status['spent'], 'amount' => $status['amount']]
                );
            } elseif ($percent >= 100) {
                $this->create(
                    $userId,
                    self::KIND_BUDGET_ALERT,
                    __('Smart notification.budget_exceeded_title', ['name' => $budget->name]),
                    __('Smart notification.budget_exceeded', ['name' => $budget->name, 'amount' => $status['spent']]),
                    $budget,
                    ['percent' => $percent, 'spent' => $status['spent'], 'amount' => $status['amount']]
                );
            }
        }
    }

    private function upcomingBills(int $userId, CarbonImmutable $reference): void
    {
        $bills = app(BillService::class)->upcoming($userId, limit: 10, from: $reference);

        foreach ($bills as $event) {
            $days = $reference->startOfDay()->diffInDays($event['date'] . ' 00:00:00');

            if ($days > 3) {
                continue;
            }

            $this->create(
                $userId,
                self::KIND_UPCOMING_BILL,
                __('Smart notification.upcoming_bill_title', ['name' => $event['bill']['name']]),
                __('Smart notification.upcoming_bill', [
                    'name' => $event['bill']['name'],
                    'amount' => $event['bill']['amount'],
                    'when' => $days === 0 ? __('Smart notification.today') : __('Smart notification.in_days', ['days' => $days]),
                ]),
                \App\Models\Bill::query()->find($event['bill']['id']),
                ['amount' => $event['bill']['amount'], 'date' => $event['date'], 'days' => $days]
            );
        }
    }

    private function goalProgress(int $userId, CarbonImmutable $reference): void
    {
        $goals = SavingsGoal::query()
            ->where('user_id', $userId)
            ->where('is_completed', false)
            ->get();

        foreach ($goals as $goal) {
            $target = Money::toCents($goal->target_amount);
            if ($target <= 0) {
                continue;
            }
            $percent = (int) round((Money::toCents($goal->current_amount) / $target) * 100);

            if ($percent > 0 && $percent % 25 === 0) {
                $this->create(
                    $userId,
                    self::KIND_GOAL_PROGRESS,
                    __('Smart notification.goal_progress_title', ['name' => $goal->name]),
                    __('Smart notification.goal_progress', ['name' => $goal->name, 'percent' => $percent]),
                    $goal,
                    ['percent' => $percent]
                );
            }
        }
    }

    private function unusualSpending(int $userId, CarbonImmutable $reference): void
    {
        $analytics = app(FinancialAnalyticsService::class);
        $compare = $analytics->compareMonths($userId, $reference);
        $delta = $compare['expense_delta_percent'] ?? 0;

        if ($delta >= 30) {
            $this->create(
                $userId,
                self::KIND_UNUSUAL_SPENDING,
                __('Smart notification.unusual_spending_title'),
                __('Smart notification.unusual_spending', [
                    'delta' => (int) $delta,
                    'amount' => $compare['expense_absolute_delta'],
                ]),
                data: ['delta_percent' => $delta]
            );
        }
    }
}