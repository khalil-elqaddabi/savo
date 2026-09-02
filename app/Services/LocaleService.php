<?php

namespace App\Services;

class LocaleService
{
    public const SUPPORTED = ['fr', 'ar', 'en'];

    private array $rtlLocales = ['ar'];

    public function isRtl(?string $locale = null): bool
    {
        return in_array($locale ?: app()->getLocale(), $this->rtlLocales, true);
    }

    public function isSupported(?string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public function name(string $locale): string
    {
        return match ($locale) {
            'fr' => 'Français',
            'ar' => 'العربية',
            'en' => 'English',
            default => $locale,
        };
    }

    public function direction(string $locale): string
    {
        return $this->isRtl($locale) ? 'rtl' : 'ltr';
    }
}
