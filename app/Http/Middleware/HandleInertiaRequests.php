<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\LocaleService;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $locale = app(LocaleService::class);

        $currentLocale = $user?->locale ?? (session('locale') ?: config('app.locale'));

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => $user->locale,
                    'theme' => $user->theme,
                    'currency' => $user->currency,
                    'email_verified_at' => $user->email_verified_at,
                    'two_factor_enabled' => (bool) ($user->two_factor_secret)
                        && (bool) $user->two_factor_confirmed_at,
                    'has_google_link' => $user->oauthIdentities()->where('provider', 'google')->exists(),
                ] : null,
            ],
            'app' => [
                'name' => config('app.name'),
                'locale' => $currentLocale,
                'dir' => $locale->direction($currentLocale),
                'isRtl' => $locale->isRtl($currentLocale),
                'theme' => $user?->theme ?: 'dark',
                'supportedLocales' => collect($locale::SUPPORTED)->map(fn ($l) => [
                    'code' => $l,
                    'name' => $locale->name($l),
                    'dir' => $locale->direction($l),
                ])->values(),
                'currency' => $user?->currency ?: 'MAD',
                'aiEnabled' => ! blank(config('services.ai.api_key')),
            ],
            'translations' => $this->translations($currentLocale),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => fn () => session('success'),
                'error' => fn () => session('error'),
                'status' => fn () => session('status'),
                'receiptDraft' => fn () => session('receiptDraft'),
            ],
        ];
    }

    private function translations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (is_file($path)) {
            $content = json_decode((string) file_get_contents($path), true);

            if (! is_array($content)) {
                return [];
            }

            // The frontend resolves keys as flat dotted paths (e.g.
            // "dashboard.greeting"), while the JSON files are authored with
            // nested objects. Flatten so every key is a dotted path and the
            // client-side t('a.b') lookups resolve.
            return $this->flatten($content);
        }

        return [];
    }

    /**
     * Recursively flatten a nested array into dotted-path keys.
     *
     * @return array<string, string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $out = [];

        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $out = array_merge($out, $this->flatten($value, $path));
            } else {
                $out[$path] = (string) $value;
            }
        }

        return $out;
    }
}
