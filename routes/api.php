<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\RecurringController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavingsGoalController;
use App\Http\Controllers\Api\SmartNotificationController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Mobile client (Expo) secured with Sanctum personal access tokens.
| Responses use a consistent shape: {"data": ...} for success and
| {"message": "...", "errors": {...}} for failures.
|
| Every route is prefixed with the `api.` name namespace so the API does not
| collide with the web route names (e.g. `accounts.store`).
|
*/

Route::name('api.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/user', [AuthController::class, 'user'])->name('auth.user');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('reports', ReportController::class)->name('reports');

        Route::apiResource('accounts', AccountController::class);
        Route::get('transactions/form-data', [TransactionController::class, 'formData'])->name('transactions.form-data');
        Route::apiResource('transactions', TransactionController::class);

        Route::apiResource('budgets', BudgetController::class);
        Route::post('budgets/{budget}/spend', [BudgetController::class, 'spend'])->name('budgets.spend');

        Route::apiResource('savings-goals', SavingsGoalController::class)
            ->parameters(['savings-goals' => 'goal']);
        Route::post('savings-goals/{goal}/contribute', [SavingsGoalController::class, 'contribute'])->name('savings-goals.contribute');

        Route::apiResource('recurring', RecurringController::class);

        Route::apiResource('bills', BillController::class)->parameters(['bills' => 'bill']);
        Route::apiResource('debts', DebtController::class)->parameters(['debts' => 'debt']);

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [SmartNotificationController::class, 'index'])->name('index');
            Route::post('{id}/read', [SmartNotificationController::class, 'markRead'])->name('mark-read');
            Route::post('mark-all-read', [SmartNotificationController::class, 'markAllRead'])->name('mark-all-read');
        });

        Route::prefix('assistant/conversations')->name('assistant.conversations')->group(function () {
            Route::get('/', [AssistantController::class, 'index'])->name('index');
            Route::post('/', [AssistantController::class, 'store'])->name('store');
            Route::get('{conversation}', [AssistantController::class, 'show'])->name('show');
            Route::delete('{conversation}', [AssistantController::class, 'destroy'])->name('destroy');
            Route::post('{conversation}/messages', [AssistantController::class, 'send'])->name('messages');
        });
    });
});