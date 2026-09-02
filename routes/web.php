<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountDataController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ReceiptScanController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\SmartNotificationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

// Google OAuth (public — allows linking while logged in)
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
Route::post('/auth/google/unlink', [GoogleController::class, 'unlink'])->middleware('auth')->name('auth.google.unlink');

// Public locale switch (persists to session for guests, to the user when signed in)
Route::post('/preferences/language', [PreferencesController::class, 'language'])->name('preferences.language');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::post('/', [AccountController::class, 'store'])->name('store');
        Route::get('/{account}', [AccountController::class, 'show'])->name('show');
        Route::put('/{account}', [AccountController::class, 'update'])->name('update');
        Route::delete('/{account}', [AccountController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/form-data', [TransactionController::class, 'formData'])->name('form-data');
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::post('/', [TransactionController::class, 'store'])->name('store');
        Route::put('/{transaction}', [TransactionController::class, 'update'])->name('update');
        Route::delete('/{transaction}', [TransactionController::class, 'destroy'])->name('destroy');
    });

    Route::resource('budgets', BudgetController::class)->except(['show', 'edit', 'create']);
    Route::post('/budgets/{budget}/members', [BudgetController::class, 'addMember'])->name('budgets.members.add');
    Route::delete('/budgets/{budget}/members/me', [BudgetController::class, 'leaveMember'])->name('budgets.members.leave');
    Route::delete('/budgets/{budget}/members/{user}', [BudgetController::class, 'removeMember'])->name('budgets.members.remove');
    Route::resource('goals', SavingsGoalController::class)->except(['show', 'edit', 'create']);
    Route::post('/goals/{goal}/contribute', [SavingsGoalController::class, 'contribute'])->name('goals.contribute');
    Route::resource('recurring', RecurringTransactionController::class)->except(['show', 'edit', 'create']);

    Route::resource('bills', BillController::class)->except(['show', 'edit', 'create']);
    Route::resource('debts', DebtController::class)->except(['show', 'edit', 'create']);

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [SmartNotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [SmartNotificationController::class, 'markRead'])->name('mark-read');
        Route::post('/mark-all-read', [SmartNotificationController::class, 'markAllRead'])->name('mark-all-read');
    });

    Route::get('/reports', ReportController::class)->name('reports');

    Route::prefix('data')->name('data.')->group(function () {
        Route::get('/export', [AccountDataController::class, 'export'])->name('export');
        Route::post('/import', [AccountDataController::class, 'import'])->name('import');
        Route::post('/reset', [AccountDataController::class, 'reset'])->name('reset');
    });

    Route::post('/receipt/scan', [ReceiptScanController::class, 'scan'])->name('receipt.scan');
    Route::get('/receipts/scan', fn () => \Inertia\Inertia::render('Transactions/ReceiptScanner'))->name('receipts.scan');

    Route::prefix('assistant')->name('assistant.')->group(function () {
        Route::get('/', [AssistantController::class, 'index'])->name('index');
        Route::get('/{conversation}', [AssistantController::class, 'show'])->name('show');
        Route::post('/create', [AssistantController::class, 'store'])->name('create');
        Route::post('/', [AssistantController::class, 'send'])->middleware('throttle:30,1')->name('send');
        Route::post('/{conversation}/send', [AssistantController::class, 'send'])->middleware('throttle:30,1')->name('send.existing');
        Route::delete('/{conversation}', [AssistantController::class, 'destroy'])->name('destroy');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/profile', fn () => \Inertia\Inertia::render('Settings/Profile'))->name('profile');
        Route::get('/security', fn () => \Inertia\Inertia::render('Settings/Security'))->name('security');
        Route::get('/appearance', fn () => \Inertia\Inertia::render('Settings/Appearance'))->name('appearance');
        Route::get('/language', fn () => \Inertia\Inertia::render('Settings/Language'))->name('language');
        Route::get('/data', fn () => \Inertia\Inertia::render('Settings/Data'))->name('data');
    });

    // Preferences
    Route::post('/preferences/theme', [PreferencesController::class, 'theme'])->name('preferences.theme');
    Route::post('/preferences/currency', [PreferencesController::class, 'currency'])->name('preferences.currency');

    // Onboarding
    Route::post('/onboarding/account', [PreferencesController::class, 'onboardAccount'])->name('onboarding.account');
    Route::post('/onboarding/skip', [PreferencesController::class, 'skipOnboarding'])->name('onboarding.skip');
});

