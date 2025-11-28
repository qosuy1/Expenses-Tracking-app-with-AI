<?php

namespace App\Livewire\Expense;

use App\Models\Expense;
use Livewire\Component;
use App\Models\Category;
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
    public $type = "one-time";

    // recurring fields
    public $recurring_frequency = 'monthly';
    public $recurring_start_date;
    public $recurring_end_date;
    public $is_auto_generated = true;

    public function mount($expenseId = null)
    {

        if ($expenseId) {
            $this->isEdit = true;
            $this->expenseId = $expenseId;
            $this->loadExpense();
        } else {
            $this->date = now()->format('Y-m-d');
        }
    }
    private function loadExpense()
    {
        $expense = Expense::forUser(auth()->user()->id)->findOrFail($this->expenseId);
        if (!$expense)
            abort(404);
        if (auth()->user()->id != $expense->user_id)
            abort(403);


        $this->amount = $expense->amount;
        $this->title = $expense->title;
        $this->date = $expense->date->format('Y-m-d');
        $this->description = $expense->description;
        $this->category_id = $expense->category_id;
        $this->type = $expense->type;

        if ($this->type === 'recurring') {
            $this->recurring_frequency = $expense->recurring_frequency;
            $this->recurring_start_date = $expense->recurring_start_date->format('Y-m-d');
            $this->recurring_end_date = $expense->recurring_end_date ? $expense->recurring_end_date->format('Y-m-d') : null;
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
            'type' => "required|in:recurring,one-time",
            'date' => 'required|date',
        ];
        if ($this->type === 'recurring') {
            $rules['recurring_frequency'] = 'required|in:daily,weekly,monthly,yearly';
            $rules['recurring_start_date'] = 'date|required';
            $rules['recurring_end_date'] = 'date|nullable|after_or_equal:recurring_start_date';
        }
        return $rules;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'user_id' => Auth::user()->id,
            'amount' => $this->amount,
            'title' => $this->title,
            'description' => $this->description,
            'date' => $this->date,
            'category_id' => $this->category_id ?: null,
            'type' => $this->type,
        ];
        if ($this->type == 'recurring') {
            $data['recurring_frequency'] = $this->recurring_frequency;
            $data['recurring_start_date'] = $this->recurring_start_date;
            $data['recurring_end_date'] = $this->recurring_end_date ?: null;
            // dd($data);
        } else {
            $data['recurring_frequency'] = null;
            $data['recurring_start_date'] = null;
            $data['recurring_end_date'] = null;
        }


        if ($this->isEdit) {
            $expense = Expense::findOrFail($this->expenseId);
            if ($expense->user_id !== Auth::user()->id) {
                abort(403);
            }
            // dd($data);
            // dd($data , ['number'=>"1"]);

            $expense->forceFill($data)->save();

            session()->flash('message', 'Expense updated successfully.');
        } else {
            // dd($data, ['number' => "2"]);

            Expense::fillAndInsert($data);
            session()->flash('message', 'Expense created successfully.');
        }

        if ($this->type === 'recurring') {
            return $this->redirect(route('expenses.recurring'), navigate: true);
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
