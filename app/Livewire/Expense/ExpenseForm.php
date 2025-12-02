<?php

namespace App\Livewire\Expense;

use App\Models\Expense;
use Livewire\Component;
use App\Models\Category;
use App\Models\RecurringExpense;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;

class ExpenseForm extends Component
{
    public $isEdit = false;
    // Basic Information
    public $expenseId;
    public $amount;
    public $date;
    public $title;
    public $category_id;
    public $description;
    // Expense Type
    // public $type = "one-time";

    // recurring fields
    public $isItRecurring = false;
    public $recurring_frequency = 'monthly';
    public $recurring_start_date;
    public $recurring_end_date;
    public $is_auto_generated = true;

    public function mount($expenseId = null)
    {

        if ($expenseId) {
            $this->isEdit = true;
            $this->isItRecurring = request()->get('isItRecurring', false);
            $this->expenseId = $expenseId;
            $this->loadExpense();
        } else {
            $this->date = now()->format('Y-m-d');
        }
    }
    private function loadExpense()
    {

        // if the expense is one-time, we should load it
        // $expense = Expense::forUser(auth()->user()->id)->oneTime()->findOrFail($this->expenseId);
        // // dd($expense);
        // if (!$expense) {

        //     // so if the expense is not one-time, try to load it as recurring
        //     $expense = RecurringExpense::forUser(auth()->user()->id)->findOrFail($this->expenseId);
        //     $isItRecurring = true;
        // }

        if ($this->isItRecurring) {
            $expense = RecurringExpense::forUser(auth()->user()->id)->findOrFail($this->expenseId);
        } else {
            $expense = Expense::forUser(auth()->user()->id)->oneTime()->findOrFail($this->expenseId);
        }
        if (!$expense) {
            abort(404);
        }
        if (auth()->user()->id != $expense->user_id)
            abort(403);


        $this->amount = $expense->amount;
        $this->title = $expense->title;
        $this->description = $expense->description;
        $this->category_id = $expense->category_id;
        // $this->type = $expense->type;

        if ($this->isItRecurring) {
            $this->recurring_frequency = $expense->recurring_frequency;
            $this->recurring_start_date = $expense->recurring_start_date->format('Y-m-d');
            $this->recurring_end_date = $expense->recurring_end_date ? $expense->recurring_end_date->format('Y-m-d') : null;
        } else {
            $this->date = $expense->date->format('Y-m-d');
        }
    }

    #[Computed]
    public function categories()
    {
        return Category::where('user_id', auth()->user()->id)->get();
    }

    public function rules()
    {

        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'nullable|exists:categories,id',
            'title' => "string|required",
            'description' => "string|nullable",
            // 'type' => "required|in:recurring,one-time",
        ];
        if ($this->isItRecurring) {
            $rules['recurring_frequency'] = 'required|in:daily,weekly,monthly,yearly';
            $rules['recurring_start_date'] = 'date|required';
            $rules['recurring_end_date'] = 'date|nullable|after_or_equal:recurring_start_date';
        } else {
            $rules['date'] = 'required|date';
        }
        return $rules;
    }

    public function save()
    {
        $this->validate();


        if ($this->isItRecurring) {

            $data = [
                'user_id' => Auth::user()->id,
                'amount' => $this->amount,
                'title' => $this->title,
                'description' => $this->description,
                // 'date' => $this->date,
                'category_id' => $this->category_id ?: null,

                'recurring_frequency' => $this->recurring_frequency,
                'recurring_start_date' => $this->recurring_start_date,
                'recurring_end_date' => $this->recurring_end_date ?: null,
            ];

            if ($this->isEdit) {
                $expense = RecurringExpense::findOrFail($this->expenseId);
                if ($expense->user_id !== Auth::user()->id) {
                    abort(403);
                }
                // dd($data);
                // dd($data , ['number'=>"1"]);

                $expense->forceFill($data)->save();

                session()->flash('message', 'Recurring Expense updated successfully.');
            } else {
                // dd($data, ['number' => "2"]);

                RecurringExpense::fillAndInsert($data);
                session()->flash('message', 'Recurring Expense created successfully.');
            }
            return $this->redirect(route('expenses.recurring'), navigate: true);
        } else {
            $data = [
                'user_id' => Auth::user()->id,
                'amount' => $this->amount,
                'title' => $this->title,
                'description' => $this->description,
                'date' => $this->date,
                'category_id' => $this->category_id ?: null,
                'is_auto_generated' => false,
                'recurring_expense_id' => null,
            ];


            if ($this->isEdit) {
                $expense = Expense::findOrFail($this->expenseId);
                if ($expense->user_id !== Auth::user()->id) {
                    abort(403);
                }

                $expense->forceFill($data)->save();

                session()->flash('message', 'Expense updated successfully.');
            } else {

                Expense::fillAndInsert($data);
                session()->flash('message', 'Expense created successfully.');
            }
        }
        return $this->redirect(route('expenses.index'), navigate: true);
    }

    public function render()
    {
        return view(
            'livewire.expense.expense-form',
            [
                'categories' => $this->categories,
            ]
        );
    }
}
