<?php

namespace App\Http\Middleware;

use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = $user?->locale ?: $request->session()->get('locale');

        if ($locale && app(LocaleService::class)->isSupported($locale)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
