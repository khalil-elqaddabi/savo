<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inertiaTranslations(string $html): array
{
    preg_match('/data-page="([^"]*)"/', $html, $m);

    if (! isset($m[1])) {
        fail('No Inertia data-page attribute found.');
    }

    $payload = json_decode(html_entity_decode($m[1]), true, 512, JSON_THROW_ON_ERROR);

    return $payload['props']['translations'] ?? [];
}

test('shared translations are flattened into dotted paths', function () {
    $user = User::factory()->create(['locale' => 'fr', 'theme' => 'light', 'currency' => 'MAD']);

    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

    $t = inertiaTranslations($html);

    expect($t)->not->toBeEmpty()
        ->and($t['dashboard.greeting'] ?? null)->not->toBeNull()
        ->and($t['nav.dashboard'] ?? null)->not->toBeNull();
});

test('nested sections no longer leak as raw objects', function () {
    $user = User::factory()->create(['locale' => 'en', 'theme' => 'light', 'currency' => 'MAD']);

    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

    $t = inertiaTranslations($html);

    expect(is_array($t['dashboard'] ?? null))->toBeFalse();
});

test('every shared translation value is a plain string', function () {
    $user = User::factory()->create(['locale' => 'ar', 'theme' => 'light', 'currency' => 'MAD']);

    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

    foreach (inertiaTranslations($html) as $value) {
        expect($value)->toBeString();
    }
});

test('interpolation keys use the {param} form supported by the client renderer', function (string $locale) {
    $user = User::factory()->create(['locale' => $locale, 'theme' => 'light', 'currency' => 'MAD']);

    $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

    $translations = inertiaTranslations($html);

    // The client translation renderer interpolates {param} (curly braces), the
    // exact form used across every locale's JSON files. Guard that the values
    // rendered for these interpolated keys never carry a literal placeholder
    // ({n}, {amount}, {date}) nor a stale Laravel ":param" form.
    $keys = ['goals.days_left', 'dashboard.safe_daily', 'dashboard.until'];

    foreach ($keys as $key) {
        expect($translations[$key] ?? null)->toBeString();

        $value = $translations[$key];
        expect($value)->not->toContain(':n')
            ->and($value)->not->toContain(':amount')
            ->and($value)->not->toContain(':date')
            ->and($value)->toMatch('/\{[a-z]+\}/');
    }
})->with(['en', 'fr', 'ar']);
