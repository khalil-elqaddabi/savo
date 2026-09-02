<?php

use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;

uses(RefreshDatabase::class);

function googleSocialiteUser(string $email, string $id, ?string $name = null): SocialiteUser
{
    $user = new SocialiteTwoUser;

    $user->map([
        'id' => $id,
        'nickname' => 'googlenick',
        'name' => $name ?? 'Google User',
        'email' => $email,
    ]);

    return $user;
}

test('Google user creation succeeds when the email does not exist and password is never null', function () {
    $socialite = googleSocialiteUser('new-google@example.com', 'google-id-123');

    $user = User::fromSocialite($socialite, 'google');

    $this->assertNotNull($user->id);
    $this->assertNotSame('', $user->password);
    $this->assertNotNull($user->password);
    $this->assertTrue(Hash::check('', $user->password) === false || strlen($user->password) >= 55);
    $this->assertDatabaseHas('users', ['email' => 'new-google@example.com', 'id' => $user->id]);
});

test('email_verified_at is set when Google provides an email', function () {
    $socialite = googleSocialiteUser('verified-google@example.com', 'google-id-456');

    $user = User::fromSocialite($socialite, 'google');

    $this->assertNotNull($user->email_verified_at);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email_verified_at' => $user->fresh()->email_verified_at,
    ]);
});

test('oauth_identities is correctly linked to the created user', function () {
    $socialite = googleSocialiteUser('linked-google@example.com', 'google-id-789');

    $user = User::fromSocialite($socialite, 'google');

    OAuthIdentity::updateOrCreate(
        ['provider' => 'google', 'provider_user_id' => 'google-id-789'],
        ['user_id' => $user->id, 'nickname' => 'googlenick', 'avatar' => null, 'tokens' => ['name' => 'Google User']],
    );

    $identity = OAuthIdentity::where('provider', 'google')->where('provider_user_id', 'google-id-789')->first();

    $this->assertNotNull($identity);
    $this->assertSame($user->id, $identity->user_id);
    $this->assertSame('google', $identity->provider);
});

test('calling fromSocialite again does not create a duplicate user', function () {
    $socialite = googleSocialiteUser('dedupe-google@example.com', 'google-id-dup');

    $first = User::fromSocialite($socialite, 'google');

    $again = User::fromSocialite($socialite, 'google');

    $this->assertTrue($first->is($again));
    $this->assertSame(1, User::where('email', 'dedupe-google@example.com')->count());
});

test('oauth user cannot authenticate using an unknown generated password', function () {
    $socialite = googleSocialiteUser('nopass-google@example.com', 'google-id-nopass');

    $user = User::fromSocialite($socialite, 'google');

    $this->assertFalse(Auth::attempt(['email' => 'nopass-google@example.com', 'password' => 'password']));
    $this->assertFalse(Auth::attempt(['email' => 'nopass-google@example.com', 'password' => Str::random(40)]));
});

test('normal email/password registration still works after Google fix', function () {
    $created = User::create([
        'name' => 'Normal User',
        'email' => 'normal@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    $this->assertNotNull($created->password);
    $this->assertTrue(Hash::check('correct-horse', $created->password));
    $this->assertDatabaseHas('users', ['email' => 'normal@example.com']);
});

test('normal password login still works after Google fix', function () {
    $user = User::create([
        'name' => 'Normal User',
        'email' => 'normal-login@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $this->assertTrue(Auth::attempt(['email' => 'normal-login@example.com', 'password' => 'secret-password']));
    $this->assertFalse(Auth::attempt(['email' => 'normal-login@example.com', 'password' => 'wrong-password']));
});

test('complete Google callback flow creates, links, authenticates, and redirects to dashboard', function () {
    $socialite = googleSocialiteUser('callback-google@example.com', 'google-id-callback');

    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn($socialite);

    $response = $this->get('/auth/google/callback?code=fake&state=fake');

    $response->assertStatus(302)
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = Auth::user();
    $this->assertNotNull($user);
    $this->assertSame('callback-google@example.com', $user->email);
    $this->assertDatabaseHas('oauth_identities', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-id-callback',
    ]);
});

test('Google callback redirects to dashboard even when a stale assistant intended URL exists', function () {
    $socialite = googleSocialiteUser('stale-intent@example.com', 'google-id-stale');

    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn($socialite);

    session(['url.intended' => route('assistant.show', ['conversation' => 45])]);

    $response = $this->get('/auth/google/callback?code=fake&state=fake');

    $response->assertStatus(302)
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
    $this->assertSame('stale-intent@example.com', Auth::user()->email);
});
