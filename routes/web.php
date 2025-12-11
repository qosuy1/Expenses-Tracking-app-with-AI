<?php

use App\Livewire\BudgetForm;
use App\Livewire\Budgetlist;
use App\Livewire\Categories;
use App\Livewire\Dashboard;
use Laravel\Fortify\Features;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\TwoFactor;
use App\Livewire\Expense\ExpenseForm;
use App\Livewire\Expense\ExpenseList;
use App\Livewire\Settings\Appearance;
use Illuminate\Support\Facades\Route;
use App\Livewire\Expense\RecurringExpense;
use App\Services\BudgetAiService;

Route::get('/', function () {
    return view('welcome');
})->name('home');


Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('dashboard', Dashboard::class)
        ->name('dashboard');


    // Ai
    Route::get('api/v1/ai', function () {
        $ai = new BudgetAiService(3, auth()->id(), 12, 2025);
        // dd($ai->hasEnoughHistoricalData(3));
        $recommendationArray = $ai->getBudgetRecommendation();
        dd($recommendationArray);
    });

    // Category Routes
    Route::get('/categories', Categories::class)->name('categories.index');

    // Budget Routes
    Route::get('/budgets', Budgetlist::class)->name('budgets.index');
    Route::get('/budgets/create', BudgetForm::class)->name('budgets.create');
    Route::get('/budgets/{budgetId}/edit', BudgetForm::class)->name('budgets.edit');

    // Expense Routes
    Route::get('/expenses', ExpenseList::class)->name('expenses.index');
    Route::get('/expenses/create', ExpenseForm::class)->name('expenses.create');
    Route::get('/expenses/{expenseId}/edit', ExpenseForm::class)->name('expenses.edit');
    Route::get('/expenses/recurring', RecurringExpense::class)->name('expenses.recurring');
    // Settings Routes
    Route::redirect('settings', 'settings/profile');
    Route::get('settings/profile', Profile::class)->name('profile.edit');
    Route::get('settings/password', Password::class)->name('user-password.edit');
    Route::get('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::get('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});
