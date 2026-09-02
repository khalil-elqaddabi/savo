<?php

use App\Support\Money;

test('toCents converts decimal strings to integer cents', function () {
    expect(Money::toCents('10.00'))->toBe(1000)
        ->and(Money::toCents('0.01'))->toBe(1)
        ->and(Money::toCents('123.45'))->toBe(12345);
});

test('toCents handles negatives and null', function () {
    expect(Money::toCents('-25.50'))->toBe(-2550)
        ->and(Money::toCents(null))->toBe(0)
        ->and(Money::toCents(''))->toBe(0);
});

test('toCents rounds half up beyond two decimals', function () {
    expect(Money::toCents('10.005'))->toBe(1001)
        ->and(Money::toCents('10.004'))->toBe(1000);
});

test('fromCents is inverse of toCents', function () {
    expect(Money::fromCents(1000))->toBe('10.00');
    expect(Money::fromCents(-2550))->toBe('-25.50');
    expect(Money::fromCents(1))->toBe('0.01');
});

test('add and sub keep integer precision', function () {
    expect(Money::add('1.10', '2.20', '0.05'))->toBe('3.35');
    expect(Money::sub('10.00', '3.25'))->toBe('6.75');
    expect(Money::add('0.1', '0.2'))->toBe('0.30');
});

test('mul and div round half up', function () {
    expect(Money::mul('10.00', 0.5))->toBe('5.00');
    expect(Money::div('10.00', 3))->toBe('3.33');
    expect(Money::div('10.00', 0))->toBe('0.00');
});

test('compare and isZero', function () {
    expect(Money::compare('2.00', '1.50'))->toBe(1);
    expect(Money::compare('1.50', '1.50'))->toBe(0);
    expect(Money::compare('1.00', '2.00'))->toBe(-1);
    expect(Money::isZero('0.00'))->toBeTrue();
    expect(Money::isZero('0.01'))->toBeFalse();
});
