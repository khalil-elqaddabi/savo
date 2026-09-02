<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sharedBudgetsUsers(): array
{
    $owner = User::factory()->create(['locale' => 'en', 'currency' => 'MAD', 'email' => 'owner@example.com']);
    $member = User::factory()->create(['locale' => 'en', 'currency' => 'MAD', 'email' => 'member@example.com']);
    $stranger = User::factory()->create(['locale' => 'en', 'currency' => 'MAD', 'email' => 'stranger@example.com']);

    return [$owner, $member, $stranger];
}

function sharedBudgetAccount(User $owner): Account
{
    return Account::create([
        'user_id' => $owner->id,
        'name' => 'Checking',
        'type' => 'bank',
        'starting_balance' => '5000.00',
        'balance' => '5000.00',
    ]);
}

test('owner creates a personal budget and sees it as owner', function () {
    [$owner] = sharedBudgetsUsers();

    $this->actingAs($owner)
        ->post('/budgets', [
            'name' => 'Groceries',
            'scope' => 'overall',
            'period' => 'monthly',
            'amount' => '500.00',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->get('/budgets')
        ->assertInertia(fn ($page) => $page
            ->component('Budgets/Index')
            ->has('budgets', 1)
            ->where('budgets.0.name', 'Groceries')
            ->where('budgets.0.is_owner', true)
            ->where('budgets.0.members', []));
});

test('owner can add a member by email', function () {
    [$owner, $member] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Home',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->post("/budgets/{$budget->id}/members", [
            'email' => $member->email,
            'role' => 'viewer',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('budget_shares', [
        'budget_id' => $budget->id,
        'user_id' => $member->id,
        'role' => 'viewer',
    ]);
});

test('member can view the shared budget in index', function () {
    [$owner, $member] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Family',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $member->id, 'role' => 'viewer']);

    $this->actingAs($member)
        ->get('/budgets')
        ->assertInertia(fn ($page) => $page
            ->has('budgets', 1)
            ->where('budgets.0.is_owner', false)
            ->where('budgets.0.name', 'Family'));
});

test('member cannot add or remove members', function () {
    [$owner, $member, $stranger] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Family',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $member->id, 'role' => 'viewer']);

    $this->actingAs($member)
        ->post("/budgets/{$budget->id}/members", ['email' => $stranger->email, 'role' => 'viewer'])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete("/budgets/{$budget->id}/members/{$stranger->id}")
        ->assertForbidden();
});

test('an editor can update the shared budget but cannot delete it or manage members', function () {
    [$owner, $member, $stranger] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Family',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $member->id, 'role' => 'editor']);

    // Editor may update the budget.
    $this->actingAs($member)
        ->put("/budgets/{$budget->id}", ['name' => 'Family Plus', 'amount' => '500.00', 'period' => 'monthly', 'scope' => 'overall'])
        ->assertSessionHas('success');
    $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'name' => 'Family Plus']);

    // Editor may NOT delete the budget.
    $this->actingAs($member)
        ->delete("/budgets/{$budget->id}")
        ->assertForbidden();
    $this->assertDatabaseHas('budgets', ['id' => $budget->id]);

    // Editor may NOT add or remove members (owner-only).
    $this->actingAs($member)
        ->post("/budgets/{$budget->id}/members", ['email' => $stranger->email, 'role' => 'viewer'])
        ->assertForbidden();
    $this->actingAs($member)
        ->delete("/budgets/{$budget->id}/members/{$stranger->id}")
        ->assertForbidden();

    $this->assertTrue($budget->members()->count() === 1);
});

test('a viewer cannot update, delete, or manage the shared budget', function () {
    [$owner, $viewer, $stranger] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Private',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);

    $this->actingAs($viewer)
        ->put("/budgets/{$budget->id}", ['name' => 'Hijacked', 'amount' => '500.00', 'period' => 'monthly', 'scope' => 'overall'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete("/budgets/{$budget->id}")
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post("/budgets/{$budget->id}/members", ['email' => $stranger->email, 'role' => 'viewer'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete("/budgets/{$budget->id}/members/{$stranger->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'name' => 'Private']);
});

test('a viewer can open and view the shared budget normally', function () {
    [$owner, $viewer] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Visible',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);

    $this->actingAs($viewer)
        ->get('/budgets')
        ->assertInertia(fn ($page) => $page
            ->has('budgets', 1)
            ->where('budgets.0.name', 'Visible')
            ->where('budgets.0.is_owner', false)
            ->where('budgets.0.role', 'viewer'));
});

test('a stranger cannot view or manage the budget', function () {
    [$owner, , $stranger] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Private',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);

    $this->actingAs($stranger)
        ->post("/budgets/{$budget->id}/members", ['email' => $owner->email, 'role' => 'viewer'])
        ->assertForbidden();

    // Stranger's budget index must not include the owner's private budget.
    $this->actingAs($stranger)
        ->get('/budgets')
        ->assertInertia(fn ($page) => $page->where('budgets', []));

    $this->assertTrue($budget->members()->count() === 0);
});

test('shared budget aggregates spending across members; personal budget does not', function () {
    [$owner, $member] = sharedBudgetsUsers();
    $ownerAccount = sharedBudgetAccount($owner);
    $memberAccount = sharedBudgetAccount($member);

    $shared = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Shared Food',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $shared->members()->create(['user_id' => $member->id, 'role' => 'viewer']);

    $personal = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Personal Food',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);

    $today = now()->toDateString();
    app(TransactionService::class)->createExpense($ownerAccount, ['amount' => '100.00', 'date' => $today]);
    app(TransactionService::class)->createExpense($memberAccount, ['amount' => '250.00', 'date' => $today]);

    $service = app(BudgetService::class);
    $sharedStatus = $service->status($shared);
    $personalStatus = $service->status($personal);

    // The shared budget reflects both owners and members spending.
    expect($sharedStatus['spent'])->toBe('350.00');
    // The personal budget only reflects the owner's own spending.
    expect($personalStatus['spent'])->toBe('100.00');
});

test('addMember rejects unknown email and the budget owner', function () {
    [$owner] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Home',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->post("/budgets/{$budget->id}/members", ['email' => 'ghost@example.com', 'role' => 'viewer'])
        ->assertSessionHasErrors('email');

    $this->actingAs($owner)
        ->post("/budgets/{$budget->id}/members", ['email' => $owner->email, 'role' => 'viewer'])
        ->assertSessionHasErrors('email');
});

test('the owner can update the budget and sees the owner role', function () {
    [$owner] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Home',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->put("/budgets/{$budget->id}", ['name' => 'Home v2', 'amount' => '800.00', 'period' => 'monthly', 'scope' => 'overall'])
        ->assertSessionHas('success');
    $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'name' => 'Home v2']);

    $this->actingAs($owner)
        ->get('/budgets')
        ->assertInertia(fn ($page) => $page
            ->where('budgets.0.role', 'owner')
            ->where('budgets.0.is_owner', true));
});

test('an editor sees the editor role in the budget index', function () {
    [$owner, $editor] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Team',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $editor->id, 'role' => 'editor']);

    $this->actingAs($editor)
        ->get('/budgets')
        ->assertInertia(fn ($page) => $page
            ->where('budgets.0.role', 'editor')
            ->where('budgets.0.is_owner', false));
});

test('a viewer cannot record budget spending via the api spend endpoint', function () {
    [$owner, $viewer] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Spendy',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);
    $account = sharedBudgetAccount($owner);

    $this->actingAs($viewer)->postJson("/api/budgets/{$budget->id}/spend", [
        'account_id' => $account->id,
        'amount' => '50.00',
    ])->assertForbidden();
});

test('an editor can record budget spending via the api spend endpoint', function () {
    [$owner, $editor] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Spendy',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $editor->id, 'role' => 'editor']);
    $account = sharedBudgetAccount($editor);

    $this->actingAs($editor)->postJson("/api/budgets/{$budget->id}/spend", [
        'account_id' => $account->id,
        'amount' => '50.00',
    ])->assertCreated();
});

test('a viewer can leave their shared budget', function () {
    [$owner, $viewer] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Shared',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $viewer->id, 'role' => 'viewer']);

    $this->actingAs($viewer)
        ->delete("/budgets/{$budget->id}/members/me")
        ->assertSessionHas('success');

    // Only the viewer's membership row is gone; budget and owner remain.
    expect(\App\Models\BudgetShare::where('user_id', $viewer->id)->count())->toBe(0)
        ->and(Budget::where('id', $budget->id)->count())->toBe(1)
        ->and(Budget::where('user_id', $owner->id)->count())->toBe(1);
});

test('an editor can leave their shared budget', function () {
    [$owner, $editor] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Team',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $editor->id, 'role' => 'editor']);

    $this->actingAs($editor)
        ->delete("/budgets/{$budget->id}/members/me")
        ->assertSessionHas('success');

    expect(\App\Models\BudgetShare::where('user_id', $editor->id)->count())->toBe(0)
        ->and(Budget::where('id', $budget->id)->count())->toBe(1);
});

test('leaving removes only the leaving users share row and keeps other members', function () {
    [$owner, $leaver, $otherMember] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Shared',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $leaver->id, 'role' => 'viewer']);
    $budget->members()->create(['user_id' => $otherMember->id, 'role' => 'editor']);

    $this->actingAs($leaver)
        ->delete("/budgets/{$budget->id}/members/me")
        ->assertSessionHas('success');

    expect(\App\Models\BudgetShare::where('user_id', $leaver->id)->count())->toBe(0)
        ->and(\App\Models\BudgetShare::where('budget_id', $budget->id)->where('user_id', $otherMember->id)->exists())->toBeTrue()
        ->and(Budget::where('id', $budget->id)->count())->toBe(1);
});

test('the budget owner cannot leave via the leave endpoint', function () {
    [$owner] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Mine',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->delete("/budgets/{$budget->id}/members/me")
        ->assertForbidden();

    // The budget is untouched.
    expect(Budget::where('id', $budget->id)->count())->toBe(1);
});

test('a stranger cannot use the leave endpoint', function () {
    [$owner, , $stranger] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Private',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);

    $this->actingAs($stranger)
        ->delete("/budgets/{$budget->id}/members/me")
        ->assertForbidden();

    expect(\App\Models\BudgetShare::where('budget_id', $budget->id)->count())->toBe(0)
        ->and(Budget::where('id', $budget->id)->count())->toBe(1);
});

test('after leaving, the budget no longer appears in the users shared budgets', function () {
    [$owner, $member] = sharedBudgetsUsers();
    $budget = Budget::create([
        'user_id' => $owner->id,
        'name' => 'Gone',
        'scope' => 'overall',
        'period' => 'monthly',
        'amount' => '1000.00',
        'is_active' => true,
    ]);
    $budget->members()->create(['user_id' => $member->id, 'role' => 'editor']);

    $this->actingAs($member)
        ->delete("/budgets/{$budget->id}/members/me")
        ->assertSessionHas('success');

    $this->actingAs($member)
        ->get('/budgets')
        ->assertInertia(fn ($page) => $page->where('budgets', []));
});

