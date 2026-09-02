<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected away from the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('guests can view the welcome page', function () {
    $this->get('/')->assertOk();
});

test('guests can view the login page', function () {
    $this->get(route('login'))->assertOk();
});

test('guests cannot view authenticated pages', function () {
    $this->get(route('accounts.index'))->assertRedirect(route('login'));
    $this->get(route('reports'))->assertRedirect(route('login'));
});

test('a registered user can access the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('a registered user can access their accounts page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk();
});
