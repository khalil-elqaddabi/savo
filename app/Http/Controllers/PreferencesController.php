<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\SavingsGoal;
use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferencesController extends Controller
{
    public function language(Request $request, LocaleService $locales): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'in:' . implode(',', LocaleService::SUPPORTED)],
        ]);

        $user = $request->user();

        if ($user) {
            $user->locale = $data['locale'];
            $user->save();
        }

        session(['locale' => $data['locale']]);
        app()->setLocale($data['locale']);

        return back()->with('success', __('Language updated.'));
    }

    public function theme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $request->user()->forceFill(['theme' => $data['theme']])->save();

        return back()->with('success', __('Theme updated.'));
    }

    public function currency(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'max:3'],
        ]);

        $request->user()->forceFill(['currency' => $data['currency']])->save();

        return back()->with('success', __('Currency updated.'));
    }

    /**
     * Onboarding step: create the first financial account.
     */
    public function onboardAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:' . implode(',', array_keys(Account::$types))],
            'balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ]);

        $request->user()->accounts()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'starting_balance' => $data['balance'] ?? 0,
            'balance' => $data['balance'] ?? 0,
        ]);

        return redirect()->route('dashboard')->with('success', __('Welcome! Your account is ready.'));
    }

    public function skipOnboarding(Request $request): RedirectResponse
    {
        session(['onboarded' => true]);

        return redirect()->route('dashboard');
    }
}
