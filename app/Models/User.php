<?php

namespace App\Models;

use App\Services\LocaleService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Socialite\Contracts\User as SocialiteUser;

#[Fillable(['name', 'email', 'password', 'locale', 'theme', 'currency', 'phone'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasApiTokens;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function financialSetting(): HasOne
    {
        return $this->hasOne(FinancialSetting::class);
    }

    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(OAuthIdentity::class);
    }

    public function usesGoogleAuth(): bool
    {
        return $this->oauthIdentities()->where('provider', 'google')->exists();
    }

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'notifiable_id')
            ->where('notifiable_type', static::class);
    }

    public function getFinancialSetting(): FinancialSetting
    {
        return $this->financialSetting ?? $this->financialSetting()->create([
            'primary_currency' => $this->currency ?: 'MAD',
        ]);
    }

    public function activeAccounts(): HasMany
    {
        return $this->accounts()->where('is_archived', false)->where('is_active', true);
    }

    public function prefersDarkTheme(): bool
    {
        return $this->theme === 'dark';
    }

    public function isRtl(): bool
    {
        return app(LocaleService::class)->isRtl($this->locale);
    }

    public static function fromSocialite(SocialiteUser $socialite, string $provider): static
    {
        $existing = static::whereHas('oauthIdentities', function ($q) use ($provider, $socialite) {
            $q->where('provider', $provider)->where('provider_user_id', $socialite->getId());
        })->first();

        if ($existing) {
            return $existing;
        }

        return static::where('email', $socialite->getEmail())->first()
            ?? tap(static::create([
                'name' => $socialite->getName() ?: $socialite->getNickname() ?: 'Utilisateur',
                'email' => $socialite->getEmail() ?? str($socialite->getId())->append('@noemail.local')->toString(),
                'password' => Str::random(80),
            ]), function (User $user) use ($socialite) {
                $user->forceFill([
                    'email_verified_at' => $socialite->getEmail() ? now() : null,
                ])->save();
            });
    }
}
