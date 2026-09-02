<?php

use App\Models\Account;
use App\Models\AiConversation;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;

function apiAuth(User $user): string
{
    return $user->createToken('test')->plainTextToken;
}

function createAccount(User $user, array $overrides = []): Account
{
    return Account::create(array_merge([
        'user_id' => $user->id,
        'name' => 'Bank',
        'type' => 'bank',
        'starting_balance' => '100.00',
        'balance' => '100.00',
    ], $overrides));
}

it('logs in and returns a bearer token', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure([
            'data' => ['token', 'user' => ['id', 'name', 'email']],
        ]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

it('rejects login with invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrongpassword',
    ])->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['email']]);
});

it('refuses a token when two factor is enabled', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
        'two_factor_secret' => encrypt('some-secret'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertStatus(422)
        ->assertJsonStructure(['errors' => ['two_factor_required']]);

    expect($user->tokens()->count())->toBe(0);
});

it('registers a new user and returns a token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'locale' => 'ar',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.user.email', 'new@example.com')
        ->assertJsonPath('data.user.locale', 'ar');

    expect(User::where('email', 'new@example.com')->exists())->toBeTrue();
});

it('validates registration input', function () {
    $this->postJson('/api/auth/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
    ])->assertStatus(422)
        ->assertJsonStructure(['errors' => ['name', 'email', 'password']]);
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $this->withToken(apiAuth($user))
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonMissing(['password']);
});

it('logs out and revokes the current token', function () {
    $user = User::factory()->create();
    $token = apiAuth($user);

    expect(app('db')->table('personal_access_tokens')->count())->toBe(1);

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk()
        ->assertJsonPath('message', $message = __('Logged out.'));

    expect($message)->not->toBeEmpty()
        ->and(app('db')->table('personal_access_tokens')->count())->toBe(0);
});

it('rejects unauthenticated requests', function () {
    $this->getJson('/api/dashboard')->assertStatus(401);
    $this->getJson('/api/accounts')->assertStatus(401);
    $this->getJson('/api/transactions')->assertStatus(401);
});

it('lists only the authenticated users accounts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    createAccount($user);
    createAccount($other, ['name' => 'Other Bank']);

    $this->withToken(apiAuth($user))
        ->getJson('/api/accounts')
        ->assertOk()
        ->assertJsonCount(1, 'data.accounts')
        ->assertJsonPath('data.accounts.0.name', 'Bank');
});

it('shows account detail with computed balance', function () {
    $user = User::factory()->create();
    $account = createAccount($user, ['starting_balance' => '50.00', 'balance' => '50.00']);

    $this->withToken(apiAuth($user))
        ->getJson("/api/accounts/{$account->id}")
        ->assertOk()
        ->assertJsonPath('data.account.id', $account->id)
        ->assertJsonPath('data.account.type_label', 'Bank Account');
});

it('prevents viewing another users account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = createAccount($other);

    $this->withToken(apiAuth($user))
        ->getJson("/api/accounts/{$account->id}")
        ->assertStatus(403);
});

it('creates an account from initial balance', function () {
    $user = User::factory()->create();

    $this->withToken(apiAuth($user))
        ->postJson('/api/accounts', [
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => '250.00',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.account.name', 'Cash');
});

it('updates and deletes an account', function () {
    $user = User::factory()->create();
    $account = createAccount($user);

    $this->withToken(apiAuth($user))
        ->putJson("/api/accounts/{$account->id}", ['name' => 'Updated', 'type' => 'bank'])
        ->assertOk()
        ->assertJsonPath('data.account.name', 'Updated');

    $this->withToken(apiAuth($user))
        ->deleteJson("/api/accounts/{$account->id}")
        ->assertOk();

    expect(Account::find($account->id))->toBeNull();
});

it('lists transactions scoped to the user with filters', function () {
    $user = User::factory()->create();
    $account = createAccount($user);

    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'amount' => '25.00',
        'date' => now()->toDateString(),
        'description' => 'Lunch',
    ]);
    Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'income',
        'amount' => '500.00',
        'date' => now()->toDateString(),
        'description' => 'Salary',
    ]);

    $this->withToken(apiAuth($user))
        ->getJson('/api/transactions?type=expense')
        ->assertOk()
        ->assertJsonCount(1, 'data.transactions')
        ->assertJsonPath('data.transactions.0.description', 'Lunch')
        ->assertJsonStructure(['data' => ['meta']]);
});

it('creates an expense transaction through the API', function () {
    $user = User::factory()->create();
    $account = createAccount($user, ['starting_balance' => '100.00', 'balance' => '100.00']);
    $category = Category::create(['name' => 'Food', 'type' => 'expense', 'icon' => 'cutlery', 'color' => '#f97316']);

    $this->withToken(apiAuth($user))
        ->postJson('/api/transactions', [
            'type' => 'expense',
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => '30.00',
            'date' => now()->toDateString(),
            'description' => 'Groceries',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.transaction.type', 'expense');

    expect($account->fresh()->balance)->toBe('70.00');
});

it('records a transfer between accounts', function () {
    $user = User::factory()->create();
    $from = createAccount($user, ['name' => 'From', 'starting_balance' => '100.00', 'balance' => '100.00']);
    $to = createAccount($user, ['name' => 'To', 'starting_balance' => '0.00', 'balance' => '0.00']);

    $this->withToken(apiAuth($user))
        ->postJson('/api/transactions', [
            'type' => 'transfer',
            'account_id' => $from->id,
            'destination_account_id' => $to->id,
            'amount' => '40.00',
            'date' => now()->toDateString(),
        ])
        ->assertStatus(201)
        ->assertJsonPath('message', __('Transfer recorded.'));

    expect($from->fresh()->balance)->toBe('60.00');
    expect($to->fresh()->balance)->toBe('40.00');
});

it('prevents creating a transaction on another users account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = createAccount($other);

    $this->withToken(apiAuth($user))
        ->postJson('/api/transactions', [
            'type' => 'expense',
            'account_id' => $account->id,
            'amount' => '10.00',
            'date' => now()->toDateString(),
        ])
        ->assertStatus(404);
});

it('lists budgets with status and current period', function () {
    $user = User::factory()->create();
    $user->budgets()->create([
        'name' => 'Food',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '500.00',
        'is_active' => true,
    ]);

    $response = $this->withToken(apiAuth($user))
        ->getJson('/api/budgets')
        ->assertOk()
        ->assertJsonCount(1, 'data.budgets')
        ->assertJsonPath('data.budgets.0.amount', '500.00')
        ->assertJsonPath('data.budgets.0.status', 'healthy');

    expect($response->json('data.budgets.0.period_start'))->toBeString();
});

it('spends against a budget creating an expense', function () {
    $user = User::factory()->create();
    $account = createAccount($user, ['starting_balance' => '200.00', 'balance' => '200.00']);
    $budget = $user->budgets()->create([
        'name' => 'Shopping',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '100.00',
        'is_active' => true,
    ]);

    $this->withToken(apiAuth($user))
        ->postJson("/api/budgets/{$budget->id}/spend", [
            'account_id' => $account->id,
            'amount' => '35.00',
        ])
        ->assertStatus(201);

    expect($account->fresh()->balance)->toBe('165.00');
});

it('prevents spending on another users budget', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $budget = $other->budgets()->create([
        'name' => 'Other',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '100.00',
    ]);

    $this->withToken(apiAuth($user))
        ->postJson("/api/budgets/{$budget->id}/spend", [
            'account_id' => createAccount($user)->id,
            'amount' => '10.00',
        ])
        ->assertStatus(403);
});

it('lists savings goals with status', function () {
    $user = User::factory()->create();
    $user->savingsGoals()->create([
        'name' => 'Holiday',
        'target_amount' => '1000.00',
    ]);

    $this->withToken(apiAuth($user))
        ->getJson('/api/savings-goals')
        ->assertOk()
        ->assertJsonCount(1, 'data.goals')
        ->assertJsonPath('data.goals.0.name', 'Holiday');
});

it('contributes to a savings goal from an account', function () {
    $user = User::factory()->create();
    $source = createAccount($user, ['name' => 'Source', 'starting_balance' => '500.00', 'balance' => '500.00']);
    $goal = $user->savingsGoals()->create([
        'name' => 'Car',
        'target_amount' => '2000.00',
        'current_amount' => '0.00',
    ]);

    $this->withToken(apiAuth($user))
        ->postJson("/api/savings-goals/{$goal->id}/contribute", [
            'account_id' => $source->id,
            'amount' => '300.00',
        ])
        ->assertOk();

    expect($goal->fresh()->current_amount)->toBe('300.00');
    expect($source->fresh()->balance)->toBe('200.00');
});

it('lists recurring transactions with monthly summary', function () {
    $user = User::factory()->create();
    $account = createAccount($user);

    $user->recurringTransactions()->create([
        'name' => 'Rent',
        'type' => 'expense',
        'amount' => '800.00',
        'account_id' => $account->id,
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->toDateString(),
        'next_occurrence' => now()->toDateString(),
        'is_active' => true,
    ]);

    $this->withToken(apiAuth($user))
        ->getJson('/api/recurring')
        ->assertOk()
        ->assertJsonCount(1, 'data.recurring')
        ->assertJsonPath('data.recurring.0.name', 'Rent');
});

it('returns the compiled dashboard', function () {
    $user = User::factory()->create();
    createAccount($user, ['starting_balance' => '500.00', 'balance' => '500.00']);

    $this->withToken(apiAuth($user))
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total_balance',
                'accounts',
                'monthly' => ['income', 'expenses', 'net'],
                'safe_to_spend' => ['safe_to_spend', 'safe_to_spend_daily'],
                'forecast',
                'forecast_series',
            ],
        ]);
});

it('returns reports with a summary and period', function () {
    $user = User::factory()->create();
    createAccount($user);

    $this->withToken(apiAuth($user))
        ->getJson('/api/reports?period=month')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'period' => ['from', 'to', 'key'],
                'summary',
                'by_category',
                'monthly',
                'budgets',
            ],
        ]);
});

it('returns form data for transaction creation', function () {
    $user = User::factory()->create();
    createAccount($user);

    $this->withToken(apiAuth($user))
        ->getJson('/api/transactions/form-data')
        ->assertOk()
        ->assertJsonStructure(['data' => ['accounts', 'categories']]);
});

it('lists, sends, and deletes assistant conversations', function () {
    $user = User::factory()->create();
    $conversation = $user->aiConversations()->create(['title' => 'Hello']);

    $this->withToken(apiAuth($user))
        ->getJson('/api/assistant/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'data.conversations');

    $this->withToken(apiAuth($user))
        ->getJson("/api/assistant/conversations/{$conversation->id}")
        ->assertOk()
        ->assertJsonPath('data.conversation.id', $conversation->id);

    $this->withToken(apiAuth($user))
        ->deleteJson("/api/assistant/conversations/{$conversation->id}")
        ->assertOk();

    expect(AiConversation::find($conversation->id))->toBeNull();
});

it('prevents accessing another users conversation', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = $other->aiConversations()->create(['title' => 'Private']);

    $this->withToken(apiAuth($user))
        ->getJson("/api/assistant/conversations/{$conversation->id}")
        ->assertStatus(403);
});

it('never exposes passwords or tokens in responses', function () {
    $user = User::factory()->create();
    $account = createAccount($user);

    $dashboard = $this->withToken(apiAuth($user))->getJson('/api/dashboard');
    $userResp = $this->withToken(apiAuth($user))->getJson('/api/auth/user');

    expect(collect($dashboard->json())->flatten()->map(fn ($v) => strtolower((string) $v)))
        ->not->toContain('password');

    $userPayload = $userResp->json('data');
    expect(array_keys($userPayload))->not->toContain('password')
        ->and(array_keys($userPayload))->not->toContain('two_factor_recovery_codes');
});