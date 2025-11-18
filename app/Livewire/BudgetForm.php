<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Category;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class BudgetForm extends Component
{
    public $budgetId;
    public $amount = '';
    public $month;
    public $year;
    public $category_id = '';
    public $isEdit = false;
    public function mount($budgetId = null)
    {
        if ($budgetId) {
            $this->isEdit = true;
            $this->budgetId = $budgetId;

            $budget = Budget::find($budgetId);
            if ($budget) {
                $this->amount = $budget->amount;
                $this->month = $budget->month;
                $this->year = $budget->year;
                $this->category_id = $budget->category_id;
            }
        } else {
            $this->month = date('m');
            $this->year = date('Y');
        }
    }
    // roles
    public function rules()
    {
        $rules = [
            'amount' => 'required|numeric|min:0',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2000',
            'category_id' => 'nullable|exists:categories,id',
        ];

        // Check for duplicate budget
        $uniqueRule = 'unique:budgets,category_id,NULL,id,user_id,' . Auth::user()->id . ',month,' . $this->month . ',year,' . $this->year;

        if ($this->isEdit) {
            $uniqueRule = 'unique:budgets,category_id,' . $this->budgetId . ',id,user_id,' . Auth::user()->id . ',month,' . $this->month . ',year,' . $this->year;
        }

        $rules['category_id'] = $this->category_id ? 'required|exists:categories,id|' . $uniqueRule : 'nullable|' . $uniqueRule;

        return $rules;
    }
    protected $messages = [
        'amount.required' => 'Please enter a budget amount.',
        'amount.min' => 'Budget amount must be greater than 0.',
        'month.required' => 'Please select a month.',
        'year.required' => 'Please select a year.',
        'category_id.unique' => 'A budget for this category already exists for the selected month and year.',
    ];

    public function loadBudget()
    {
        $budget = Budget::find($this->budgetId);
        if (!$budget) {
            throw new \Exception("Budget not found");
        }

        $this->amount = $budget->amount;
        $this->month = $budget->month;
        $this->year = $budget->year;
        $this->category_id = $budget->category_id;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'amount' => $this->amount,
            'month' => $this->month,
            'year' => $this->year,
            'category_id' => $this->category_id ?: null,
            'user_id' => Auth::id()
        ];

        if ($this->isEdit) {
            $budget = Budget::findOrFail($this->budgetId);

            if ($budget->user_id != Auth::id()) {
                throw new \Exception("Unauthorized action.");
            }
            $budget->update($data);
            session()->flash('message', 'budget updated successfully');
        } else {
            Budget::create($data)->save();
            session()->flash('message', 'budget created successfully');
        }

        return $this->redirect(route('budgets.index'), true);
    }

    #[Computed]
    public function months()
    {
        return collect(range(1, 12))->map(function ($month) {
            $value = $month;
            $name = Carbon::create(null, $month)->format('F');
            return ['value' => $value, 'name' => $name];
        });
    }
    #[Computed]
    public function years()
    {
        $currentYear = Carbon::now()->year;
        return collect(range($currentYear - 1, $currentYear + 2));
    }
    #[Computed]
    public function categories()
    {
        return Category::where('user_id', Auth::id())
            ->orderBy('name')
            ->get();
    }
    #[Computed]
    public function getNumberOfDaysInMonth()
    {
        return Carbon::create($this->year, $this->month)->daysInMonth;
    }



    public function render()
    {
        return view('livewire.budget-form', [
            'categories' => $this->categories,
            'months' => $this->months,
            'years' => $this->years,
            'daysInMonth' => $this->getNumberOfDaysInMonth,
        ]);
    }
}
