<?php

namespace App\Livewire;

use Carbon\Month;
use Carbon\Carbon;
use App\Models\Budget;
use App\Models\Expense;
use ArielMejiaDev\LarapexCharts\LarapexChart;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

#[Title("Dashboard - ExpenseApp")]
class Dashboard extends Component
{
    public $selectedMonth;
    public $selectedYear;
    public $totalSpent;
    public $monthlyBudget;
    public $percentageUsed;

    public $expenseByCategory;

    public $recentExpenses;

    public $monthlyComparison;

    public $topCategories;
    public $recurringExpenseCount;

    public function mount()
    {
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        // Load and compute all necessary dashboard data here
        $userId = Auth::id();

        // total amount spent in a month
        $this->totalSpent = Expense::forUser($userId)
            ->inMonth($this->selectedMonth, $this->selectedYear)
            ->sum('amount');

        // get Monthly Budget
        $this->monthlyBudget = Budget::where('user_id', $userId)
            ->where('month', $this->selectedMonth)
            ->where('year', $this->selectedYear)
            ->sum('amount');

        // percentage used
        $this->percentageUsed = $this->monthlyBudget > 0 ? round(($this->totalSpent / $this->monthlyBudget) * 100, 1) : 0;

        // Expense by category
        $this->expenseByCategory = Expense::with('category')->select('categories.name', 'categories.color', DB::raw('SUM(expenses.amount) as total'))
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->where('expenses.user_id', $userId)
            ->whereMonth('expenses.date', $this->selectedMonth)
            ->whereYear('expenses.date', $this->selectedYear)
            ->groupBy('categories.name', 'categories.color')
            ->orderBy('total', 'desc')
            ->get();
        // $sqlSentance = DB::query()->select(['categories.name' , 'categories.color' , DB::raw('SUM(expenses.amount) as total')])
        // ->from('expenses')
        // ->join('categories' ,  'expenses.category_id' , '=','categories.id')
        // ->where('expenses.user_id' , '=' , $userId)
        // ->whereMonth('expenses.date' , '=' , $this->selectedMonth)
        // ->whereYear('expenses.date' , '=' , $this->selectedYear)
        // ->groupBy('categories.name' , 'categories.color')
        // ->orderBy('total' , 'desc')
        // ->get();
        // dd($sqlSentance);

        // Recent Expenses
        $this->recentExpenses = Expense::forUser($userId)
            ->inMonth($this->selectedMonth, $this->selectedYear)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();

        // Monthly Comparison
        $this->monthlyComparison = collect();
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->subMonths($i);
            $amount = Expense::forUser($userId)
                ->inMonth($date->month, $date->year)
                ->sum('amount');
            $this->monthlyComparison->push([
                'month' => $date->format('M'),
                'total' => $amount,
            ]);
        }
        // dd($this->monthlyComparison);

        // Top Categories
        $this->topCategories = $this->expenseByCategory->take(3);

        // Recurring expense count
        $this->recurringExpenseCount = Expense::forUser($userId)->inMonth($this->selectedMonth, $this->selectedYear)->recurring()->count();

        $this->dispatch(
            'dashboard-charts-updated',
            months: $this->monthlyComparison->pluck('month')->values(),
            totals: $this->monthlyComparison->pluck('total')->values(),

            labels: $this->expenseByCategory->pluck('name')->values(),
            values: $this->expenseByCategory->pluck('total')->values(),
            colors: $this->expenseByCategory->pluck('color')->values()
        );
    }

    public function setCurrentMonth()
    {
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
        $this->loadDashboardData();
    }
    public function previousMonth()
    {
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->subMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
        $this->loadDashboardData();
    }

    public function nextMonth()
    {
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->addMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
        $this->loadDashboardData();
    }


    public function render()
    {
        return view('livewire.dashboard');
    }
}
