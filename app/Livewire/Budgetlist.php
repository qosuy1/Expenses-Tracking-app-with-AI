<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Budgetlist extends Component
{
    public $selectedMonth = "";
    public $selectedYear = "";
    public $showCreateModel = false;

    public function mount()
    {
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
    }

    #[Computed]
    public function budgets()
    {
        return Budget::with('category')
            ->where('user_id', Auth::id())
            ->where('month', $this->selectedMonth)
            ->where('year', $this->selectedYear)
            ->get()
            ->map(function ($budget) {
                $budget->spent = $budget->spent_amount;
                $budget->remaining = $budget->remaining_amount;
                $budget->percentage = $budget->getPercentageUsed();
                $budget->is_over = $budget->isOverBudget();
                return $budget;
            });
    }
    #[Computed]
    public function categories()
    {
        return Category::where('user_id', Auth::id())->orderBy('name')->get();
    }

    #[Computed]
    public function totalBudget()
    {
        return $this->budgets()->sum('amount');
    }
    #[Computed]
    public function totalSpent()
    {
        return $this->budgets()->sum('spent');
    }
    #[Computed]
    public function totalRemainingAmount()
    {
        return $this->budgets()->sum('remaining');
    }
    #[Computed]
    public function overallUsingPercentage()
    {
        if ($this->totalSpent == 0 || $this->totalBudget == 0)
            return 0;
        return round(($this->totalSpent / $this->totalBudget) * 100, 1) ;
    }



    public function prevMonth()
    {
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->subMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
    }
    public function nextMonth()
    {
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->addMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
    }
    public function setCurrentMonth()
    {
        $this->selectedMonth = Carbon::now()->month;
        $this->selectedYear = Carbon::now()->year;
    }
    public function deleteBudget($budgetId)
    {
        Budget::findOrFail($budgetId)->delete();
        session()->flash('message', 'Budget deleted successfully');
    }



    public function render()
    {
        return view(
            'livewire.budgetlist',
            [
                'selectedYear' => $this->selectedYear,
                'selectedMonth' => $this->selectedMonth,
                'budgets' => $this->budgets,
                'catygories' => $this->categories,
                'totalBudget' => $this->totalBudget,
                'totalSpent' => $this->totalSpent,
                'totalRemainingAmount' => $this->totalRemainingAmount,
                'overallUsingPercentage' => $this->overallUsingPercentage,
            ]
        );
    }
}
