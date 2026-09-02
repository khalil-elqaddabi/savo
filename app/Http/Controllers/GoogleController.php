<?php

namespace App\Http\Controllers;

use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $socialite = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Google authentication was cancelled or failed. Please try again.']);
        }

        if (! $socialite->getEmail()) {
            return redirect()->route('login')->withErrors(['email' => 'We could not read an email address from your Google account.']);
        }

        $user = DB::transaction(function () use ($socialite, $request) {
            $user = User::fromSocialite($socialite, 'google');

            if ($request->user()) {
                $this->linkIdentity($request->user(), $socialite);

                return $request->user();
            }

            if ($user->wasRecentlyCreated || $user->wasChanged('email')) {
                $user->save();
            }

            OAuthIdentity::updateOrCreate(
                [
                    'provider' => 'google',
                    'provider_user_id' => $socialite->getId(),
                ],
                [
                    'user_id' => $user->id,
                    'nickname' => $socialite->getNickname(),
                    'avatar' => $socialite->getAvatar(),
                    'tokens' => ['name' => $socialite->getName()],
                ],
            );

            return $user;
        });

        Auth::login($user, true);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function link(Request $request): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function unlink(Request $request): RedirectResponse
    {
        $request->user()->oauthIdentities()->where('provider', 'google')->delete();

        return back()->with('success', __('Google account unlinked.'));
    }

    private function linkIdentity(User $user, $socialite): void
    {
        OAuthIdentity::updateOrCreate(
            [
                'provider' => 'google',
                'provider_user_id' => $socialite->getId(),
            ],
            [
                'user_id' => $user->id,
                'nickname' => $socialite->getNickname(),
                'avatar' => $socialite->getAvatar(),
            ],
        );
    }
}
