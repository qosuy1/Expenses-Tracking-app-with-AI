<?php

namespace App\Livewire\Expense;

use App\Models\Expense;
use App\Models\RecurringExpense as ModelsRecurringExpense;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RecurringExpense extends Component
{

    #[Computed]
    public function recurringExpenses()
    {
        // return Expense::with(['category', 'childExpenses'])->forUser(Auth::id())->recurring()->get();
        return ModelsRecurringExpense::with('category')->forUser(Auth::id());
    }
    #[Computed]
    public function monthlyRecurringTotal()
    {
        // $monthlyTotal = $this->recurringExpenses
        //     ->where('recurring_frequency', 'monthly')
        //     ->sum('amount');
        // $dailyTotalinEveryMonth = $this->recurringExpenses
        //     ->where('recurring_frequency', 'daily')
        //     ->sum('amount') * now()->dayOfMonth;
        // $weeklyTotalinEveryMonth = $this->recurringExpenses
        //     ->where('recurring_frequency', 'weekly')
        //     ->sum('amount') * (int) ceil(now()->dayOfMonth / 7);
        // $yearlyTotalinEveryMonth = $this->recurringExpenses
        //     ->where('recurring_frequency', 'yearly')
        //     ->sum('amount') / 12;
        // return $monthlyTotal + $dailyTotalinEveryMonth + $weeklyTotalinEveryMonth + $yearlyTotalinEveryMonth;

        // Aggregate sums per frequency in a single query to minimize DB round-trips
        $sums = ModelsRecurringExpense::forUser(Auth::id())
            ->active()
            ->selectRaw("SUM(CASE WHEN recurring_frequency = 'monthly' THEN amount ELSE 0 END) as monthly_sum")
            ->selectRaw("SUM(CASE WHEN recurring_frequency = 'daily' THEN amount ELSE 0 END) as daily_sum")
            ->selectRaw("SUM(CASE WHEN recurring_frequency = 'weekly' THEN amount ELSE 0 END) as weekly_sum")
            ->selectRaw("SUM(CASE WHEN recurring_frequency = 'yearly' THEN amount ELSE 0 END) as yearly_sum")
            ->first();

        $monthly = $sums->monthly_sum ?? 0;
        $daily = $sums->daily_sum ?? 0;
        $weekly = $sums->weekly_sum ?? 0;
        $yearly = $sums->yearly_sum ?? 0;

        $daysThisMonth = now()->dayOfMonth;
        $weeksThisMonth = (int) ceil($daysThisMonth / 7);

        $dailyTotalinEveryMonth = $daily * $daysThisMonth;
        $weeklyTotalinEveryMonth = $weekly * $weeksThisMonth;
        $yearlyTotalinEveryMonth = $yearly / 12;

        return $monthly + $dailyTotalinEveryMonth + $weeklyTotalinEveryMonth + $yearlyTotalinEveryMonth;
    }
    #[Computed]
    public function generatedExpensesCount()
    {
        return Expense::forUser(Auth::id())
            ->inDateRange(now()->startOfMonth(), now()->endOfMonth())
            ->recurring()->count();
    }

    public function deleteRecurringExpense($expenseid)
    {
        $expense = ModelsRecurringExpense::forUser(Auth::id())->find($expenseid);

        if (!$expense) {
            session()->flash('error', 'Recurring expense not found.');
            return;
        }

        // Delete child expenses first
        $expense->childExpenses()->each(function ($childExpense) {
            $childExpense->delete();
        });

        // Then delete the parent recurring expense
        $expense->delete();

        session()->flash('message', 'Recurring expense and its occurrences deleted successfully.');
    }

    public function render()
    {
        return view(
            'livewire.expense.recurring-expense',
            [
                'generatedExpensesCount' => $this->generatedExpensesCount,
                'recurringExpenses' => $this->recurringExpenses->latest()->get(),
                'monthlyTotal' => $this->monthlyRecurringTotal,
                'activeRecurringCount' => $this->recurringExpenses->active()->count(),
            ]
        );
    }
}
