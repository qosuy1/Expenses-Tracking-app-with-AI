<?php

namespace App\Livewire;

use Carbon\Month;
use Carbon\Carbon;
use App\Models\Budget;
use App\Models\Expense;
use Livewire\Component;
use App\Models\Category;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use ArielMejiaDev\LarapexCharts\LarapexChart;
use Illuminate\Support\Collection;

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

    // Private functions
    private function getExpenseByCategory($userId, $month, $year): Collection
    {
        $expenseByCategory = Expense::with('category')->select('categories.name', 'categories.color', DB::raw('SUM(expenses.amount) as total'))
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->where('expenses.user_id', $userId)
            ->whereMonth('expenses.date', $month)
            ->whereYear('expenses.date', $year)
            ->groupBy('categories.name', 'categories.color')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'color' => $item->color,
                    'total' => $item->total
                ];
            })->collect();

        // Add Overall Budget to the array
        $overallBudgetSpent = $this->overallBudgetSpent();
        if ($overallBudgetSpent > 0) {
            $expenseByCategory->push(
                [
                    'name' => 'Overall Budget',
                    'color' => 'oklch(44.6% 0.03 256.802)',
                    'total' => (float) round($overallBudgetSpent, 2),
                ]
            );
        }
        return $expenseByCategory;
    }
    private function getMonthlyComparison(int $userId, int $month, int $year, int $monthsCount = 6)
    {
        // Generate date range for the last N months
        $dateRange = collect();
        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $dateRange->push(Carbon::create($year, $month, 1)->subMonths($i));
        }

        // Get expenses grouped by month
        $monthsSpent = Expense::query()
            ->select(
                DB::raw("SUM(amount) as total"),
                DB::raw("MONTH(date) as month"),
                DB::raw("YEAR(date) as year")
            )
            ->forUser($userId)
            ->inDateRange($dateRange->first(), $dateRange->last()->copy()->endOfMonth())
            ->groupBy('month', 'year')
            ->get()
            ->map(function ($expense) {
                return [
                    'total' => (float) $expense->total,
                    'month' => (int) $expense->month  // Keep numeric for comparison
                ];
            });

        // Ensure all months in the range are present with total = 0 if no data exists
        // Format month names for chart display
        $monthsMap = collect();
        foreach ($dateRange as $monthDate) {
            $found = $monthsSpent->firstWhere('month', $monthDate->month);
            if ($found) {
                $monthsMap->push([
                    'total' => $found['total'],
                    'month' => $monthDate->format('M')
                ]);
            } else {
                $monthsMap->push([
                    'total' => 0,
                    'month' => $monthDate->format('M')
                ]);
            }
        }

        return $monthsMap;
    }
    private function overallBudgetSpent()
    {
        return (float) Expense::forUser(Auth::id())
            ->inMonth($this->selectedMonth, $this->selectedYear)
            ->where('category_id', null)
            ->sum('amount');
    }

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
        $this->expenseByCategory = $this->getExpenseByCategory($userId, $this->selectedMonth, $this->selectedYear);
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
        $this->monthlyComparison = $this->getMonthlyComparison($userId, $this->selectedMonth, $this->selectedYear);

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
