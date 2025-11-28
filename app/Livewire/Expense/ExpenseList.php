<?php

namespace App\Livewire\Expense;

use App\Models\Category;
use App\Models\Expense;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ExpenseList extends Component
{
    // public properties
    public $search = "";
    public $selectedCategory = null;
    public $startDate = null;
    public $endDate = null;
    public $sortField = 'date';
    public $sortDirection = 'desc';
    public $showFilters = false;
    public $onlyOneTimeExpenses = false;


    public function mount()
    {
        if (empty($this->startDate)) {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
        }
        if (empty($this->endDate)) {
            $this->endDate = now()->endOfMonth()->format('Y-m-d');
        }
    }

    // Computed property of expense
    #[Computed]
    public function expenses()
    {
        return Expense::with('category')->forUser(auth()->user()->id)
            ->orderBy($this->sortField, $this->sortDirection)

            // add sort and search filters
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->when($this->selectedCategory, function ($query) {
                $query->where('category_id', $this->selectedCategory);
            })
            ->when($this->startDate, function ($query) {
                $query->whereDate('date', '>=', $this->startDate);
            })
            ->when($this->endDate, function ($query) {
                $query->whereDate('date', '<=', $this->endDate);
            })
            ->when($this->onlyOneTimeExpenses, function ($query) {
                $query->where('type', 'one-time');
            })
            ->paginate(10);
    }

    #[Computed]
    public function total()
    {
        return $this->expenses->sum('amount');
    }

    #[Computed]
    public function categories()
    {
        return Category::where('user_id', auth()->id())->get();
    }

    // sorting data
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }
    // deleteing the expense
    public function deleteExpense($expenseId)
    {
        $expense = Expense::forUser(auth()->user())->find($expenseId);
        if ($expense) {
            $expense->delete();
            session()->flash('message', 'Expense deleted successfully.');
        } else {
            session()->flash('error', 'Expense not found or you do not have permission to delete it.');
        }
    }

    public function clearFilters()
    {
        $this->search = "";
        $this->selectedCategory = null;
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function render()
    {
        return view(
            'livewire.expense.expense-list',
            [
                'expenses' => $this->expenses,
                'total' => $this->total,
                'categories' => $this->categories,
            ]
        );
    }
}
