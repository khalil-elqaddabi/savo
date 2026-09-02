<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialSetting;
use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const SYSTEM_CATEGORIES = [
        // expense
        ['name' => 'Groceries', 'type' => 'expense', 'icon' => 'cart', 'color' => '#10b981'],
        ['name' => 'Housing', 'type' => 'expense', 'icon' => 'home', 'color' => '#0ea5e9'],
        ['name' => 'Transport', 'type' => 'expense', 'icon' => 'car', 'color' => '#f59e0b'],
        ['name' => 'Utilities', 'type' => 'expense', 'icon' => 'bolt', 'color' => '#facc15'],
        ['name' => 'Dining', 'type' => 'expense', 'icon' => 'utensils', 'color' => '#8b5cf6'],
        ['name' => 'Entertainment', 'type' => 'expense', 'icon' => 'film', 'color' => '#ec4899'],
        ['name' => 'Health', 'type' => 'expense', 'icon' => 'heart', 'color' => '#ef4444'],
        ['name' => 'Shopping', 'type' => 'expense', 'icon' => 'bag', 'color' => '#6366f1'],
        ['name' => 'Subscriptions', 'type' => 'expense', 'icon' => 'repeat', 'color' => '#14b8a6'],
        ['name' => 'Education', 'type' => 'expense', 'icon' => 'book', 'color' => '#06b6d4'],
        // income
        ['name' => 'Salary', 'type' => 'income', 'icon' => 'briefcase', 'color' => '#10b981'],
        ['name' => 'Freelance', 'type' => 'income', 'icon' => 'laptop', 'color' => '#0ea5e9'],
        ['name' => 'Other income', 'type' => 'income', 'icon' => 'gift', 'color' => '#f59e0b'],
    ];

    public function run(): void
    {
        $this->seedSystemCategories();

        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@savo.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'locale' => 'fr',
            'theme' => 'light',
            'currency' => 'MAD',
        ]);

        $this->seedFinancialData($user);
    }

    private function seedSystemCategories(): void
    {
        foreach (self::SYSTEM_CATEGORIES as $c) {
            Category::query()->updateOrCreate(
                ['user_id' => null, 'name' => $c['name'], 'type' => $c['type']],
                [...$c, 'is_system' => true, 'is_active' => true],
            );
        }
    }

    private function seedFinancialData(User $user): void
    {
        $accounts = [
            'cash' => Account::query()->create([
                'user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash',
                'starting_balance' => '800.00', 'balance' => '800.00',
                'icon' => 'cash', 'color' => '#10b981', 'is_active' => true,
            ]),
            'bank' => Account::query()->create([
                'user_id' => $user->id, 'name' => 'Main Bank', 'type' => 'bank',
                'starting_balance' => '2500.00', 'balance' => '2500.00',
                'icon' => 'bank', 'color' => '#0ea5e9', 'is_active' => true,
                'institution' => 'CIH Bank',
            ]),
            'savings' => Account::query()->create([
                'user_id' => $user->id, 'name' => 'Savings', 'type' => 'savings',
                'starting_balance' => '1000.00', 'balance' => '1000.00',
                'icon' => 'piggy', 'color' => '#8b5cf6', 'is_active' => true,
            ]),
            'wallet' => Account::query()->create([
                'user_id' => $user->id, 'name' => 'Digital Wallet', 'type' => 'digital_wallet',
                'starting_balance' => '300.00', 'balance' => '300.00',
                'icon' => 'phone', 'color' => '#6366f1', 'is_active' => true,
            ]),
        ];

        $cat = fn (string $name) => Category::query()->where('user_id', null)->where('name', $name)->first();

        $now = CarbonImmutable::now();

        // Recurring templates
        $recurrings = [
            [
                'name' => 'Salary', 'type' => RecurringTransaction::TYPE_INCOME, 'amount' => '9500.00',
                'account_id' => $accounts['bank']->id, 'category_id' => $cat('Salary')->id,
                'frequency' => RecurringTransaction::FREQ_MONTHLY, 'start_date' => $now->startOfMonth(),
                'next_occurrence' => $now->startOfMonth(),
            ],
            [
                'name' => 'Rent', 'type' => RecurringTransaction::TYPE_EXPENSE, 'amount' => '3500.00',
                'account_id' => $accounts['bank']->id, 'category_id' => $cat('Housing')->id,
                'frequency' => RecurringTransaction::FREQ_MONTHLY, 'start_date' => $now->startOfMonth(),
                'next_occurrence' => $now->startOfMonth(),
            ],
            [
                'name' => 'Electricity', 'type' => RecurringTransaction::TYPE_EXPENSE, 'amount' => '420.00',
                'account_id' => $accounts['bank']->id, 'category_id' => $cat('Utilities')->id,
                'frequency' => RecurringTransaction::FREQ_MONTHLY, 'start_date' => $now->startOfMonth(),
                'next_occurrence' => $now->startOfMonth(),
            ],
            [
                'name' => 'Streaming', 'type' => RecurringTransaction::TYPE_EXPENSE, 'amount' => '99.00',
                'account_id' => $accounts['bank']->id, 'category_id' => $cat('Subscriptions')->id,
                'frequency' => RecurringTransaction::FREQ_MONTHLY, 'start_date' => $now->startOfMonth(),
                'next_occurrence' => $now->startOfMonth(),
            ],
        ];

        foreach ($recurrings as $r) {
            RecurringTransaction::query()->create([...$r, 'user_id' => $user->id]);
        }

        // Generate transactions for the last 3 months (current + 2 previous)
        $transactionSeed = [
            ['income', 'Salary', '9500.00', 'Monthly salary', 'Salary'],
            ['expense', 'Rent', '3500.00', 'Apartment rent', 'Housing'],
            ['expense', 'Utilities', '420.00', 'Electricity & water', 'Utilities'],
            ['expense', 'Groceries', '1240.00', 'Weekly groceries', 'Groceries'],
            ['expense', 'Groceries', '860.00', 'Supermarket', 'Groceries'],
            ['expense', 'Transport', '320.00', 'Fuel', 'Transport'],
            ['expense', 'Dining', '490.00', 'Restaurants', 'Dining'],
            ['expense', 'Entertainment', '280.00', 'Cinema night', 'Entertainment'],
            ['expense', 'Shopping', '650.00', 'Clothes', 'Shopping'],
            ['expense', 'Subscriptions', '99.00', 'Streaming', 'Subscriptions'],
            ['expense', 'Health', '240.00', 'Pharmacy', 'Health'],
            ['income', 'Freelance', '1800.00', 'Freelance project', 'Freelance'],
        ];

        for ($m = 2; $m >= 0; $m--) {
            $month = $now->subMonthsNoOverflow($m);
            foreach ($transactionSeed as [$type, $name, $amount, $desc, $category]) {
                $day = ($type === 'income' && $name === 'Salary') ? 1 : rand(1, $month->daysInMonth);
                Transaction::query()->create([
                    'user_id' => $user->id,
                    'account_id' => $type === Transaction::TYPE_EXPENSE ? $accounts['bank']->id : $accounts['bank']->id,
                    'category_id' => $cat($category)->id,
                    'type' => $type,
                    'amount' => $amount,
                    'date' => $month->startOfDay()->day($day)->toDateString(),
                    'description' => $desc,
                ]);
            }
        }

        // A couple transfers bank -> savings and bank -> wallet
        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $accounts['bank']->id,
            'destination_account_id' => $accounts['savings']->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => '500.00',
            'date' => $now->subDays(5)->toDateString(),
            'description' => 'Monthly savings transfer',
        ]);
        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $accounts['bank']->id,
            'destination_account_id' => $accounts['wallet']->id,
            'type' => Transaction::TYPE_TRANSFER,
            'amount' => '200.00',
            'date' => $now->subDays(9)->toDateString(),
            'description' => 'Wallet top-up',
        ]);

        // Budgets
        Budget::query()->create([
            'user_id' => $user->id, 'name' => 'Monthly overall', 'scope' => Budget::SCOPE_OVERALL,
            'period' => Budget::PERIOD_MONTHLY, 'amount' => '6500.00', 'is_active' => true,
        ]);
        Budget::query()->create([
            'user_id' => $user->id, 'name' => 'Groceries', 'scope' => Budget::SCOPE_CATEGORY,
            'category_id' => $cat('Groceries')->id, 'period' => Budget::PERIOD_MONTHLY,
            'amount' => '2000.00', 'is_active' => true,
        ]);
        Budget::query()->create([
            'user_id' => $user->id, 'name' => 'Dining out', 'scope' => Budget::SCOPE_CATEGORY,
            'category_id' => $cat('Dining')->id, 'period' => Budget::PERIOD_MONTHLY,
            'amount' => '800.00', 'is_active' => true,
        ]);

        // Savings goals
        SavingsGoal::query()->create([
            'user_id' => $user->id, 'name' => 'Emergency fund', 'target_amount' => '15000.00',
            'current_amount' => '6500.00', 'deadline' => $now->addMonths(8)->toDateString(),
            'icon' => 'emergency', 'color' => '#f59e0b', 'description' => '3 months of expenses',
        ]);
        SavingsGoal::query()->create([
            'user_id' => $user->id, 'name' => 'Holiday trip', 'target_amount' => '9000.00',
            'current_amount' => '1800.00', 'deadline' => $now->addMonths(4)->toDateString(),
            'icon' => 'travel', 'color' => '#10b981', 'description' => 'Summer vacation',
        ]);
        SavingsGoal::query()->create([
            'user_id' => $user->id, 'name' => 'New laptop', 'target_amount' => '5000.00',
            'current_amount' => '5000.00', 'is_completed' => true,
            'achieved_at' => $now->subDays(20), 'icon' => 'tech', 'color' => '#0ea5e9',
        ]);

        // Financial settings
        FinancialSetting::query()->create([
            'user_id' => $user->id, 'primary_currency' => 'MAD',
            'protected_money' => '500.00', 'default_savings_rate' => '15.00',
            'payday_day' => 1, 'safe_to_spend_enabled' => true,
        ]);

        // Recompute balances from the seeded ledger
        app(AccountBalanceService::class)->refreshAllForUser($user->id);
    }
}
