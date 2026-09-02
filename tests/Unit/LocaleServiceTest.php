<?php

use App\Services\LocaleService;

$service = new LocaleService();

test('Arabic is RTL, others are LTR', function () use ($service) {
    expect($service->isRtl('ar'))->toBeTrue()
        ->and($service->isRtl('fr'))->toBeFalse()
        ->and($service->isRtl('en'))->toBeFalse();
});

test('direction returns rtl/ltr', function () use ($service) {
    expect($service->direction('ar'))->toBe('rtl')
        ->and($service->direction('en'))->toBe('ltr');
});

test('supported locales', function () use ($service) {
    expect($service->isSupported('fr'))->toBeTrue()
        ->and($service->isSupported('ar'))->toBeTrue()
        ->and($service->isSupported('en'))->toBeTrue()
        ->and($service->isSupported('de'))->toBeFalse();
});

test('locale names', function () use ($service) {
    expect($service->name('fr'))->toBe('Français')
        ->and($service->name('ar'))->toBe('العربية')
        ->and($service->name('en'))->toBe('English');
});
