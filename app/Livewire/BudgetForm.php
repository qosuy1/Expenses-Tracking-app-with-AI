<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetAiService;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

class BudgetForm extends Component
{
    public $budgetId;
    public $amount = '';
    public $month;
    public $year;
    public $category_id = '';
    public $isEdit = false;


    // AI Budget Recommendation properties
    private $aiService;
    public $aiRecommendation = null;
    public $showAIRecommendation = false;
    public $loadingRecommendation = false;
    public $hasHistoricalData = false;


    public function mount($budgetId = null)
    {
        $this->aiService = new BudgetAiService($this->category_id ?: null, Auth::id(), $this->month, $this->year);

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
            // $this->aiService = new BudgetAiService((int) $this->category_id, Auth::id(), $this->month, $this->year);
            $this->checkHistoricalData();
        }
        // $this->hasHistoricalData = $this->aiService->hasEnoughHistoricalData($this->category_id);
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

    // some functions for AI Recommendation will be here
    public function updateCategoryId()
    {
        // dd($this->category_id);
        $this->aiService = new BudgetAiService($this->category_id ?: null, Auth::id(), $this->month, $this->year);
        // update category_id in the class
        // $this->aiService->categoryId = $this->category_id == 0 ? null : $this->category_id;

        $this->hasHistoricalData = $this->aiService->hasEnoughHistoricalData();
        // if($this->category_id != null)
        //     dd($this->category_id,$this->hasHistoricalData);

        // reset the Ai recommendations
        $this->aiRecommendation = null;
        $this->showAIRecommendation = false;
    }

    public function getAIRecommendation()
    {
        $this->loadingRecommendation = true;
        $this->aiRecommendation = null;

        try {
            if (!$this->aiService) {
                $this->aiService = new BudgetAiService((int) $this->category_id, Auth::id(), $this->month, $this->year);
            }


            $aiRecommendation = $this->aiService->getBudgetRecommendation();

            if ($aiRecommendation) {
                $this->aiRecommendation = $aiRecommendation;
                $this->showAIRecommendation = true;
            } else {
                session()->flash('error', 'No recommendation available.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to fetch AI recommendation. Please try again later.');
            Log::error('error : Failed to fetch AI recommendation. Please try again later.');
        } finally {
            $this->loadingRecommendation = false;
        }
    }
    public function applyRecommendation($type = 'recommended')
    {
        if ($this->aiRecommendation)
            $this->amount = $this->aiRecommendation[$type] ?? $this->aiRecommendation['recommended'];
    }
    public function closeAIRecommendation()
    {
        $this->showAIRecommendation = false;
    }


    /**
     * Check historical data when month/year changes
     */
    public function updatedMonth()
    {
        $this->checkHistoricalData();
    }

    public function updatedYear()
    {
        $this->checkHistoricalData();
    }

    private function checkHistoricalData()
    {
        // if (!$this->aiService)
        //     $this->aiService = new BudgetAiService((int) $this->category_id, Auth::id(), $this->month, $this->year);
        // if ($this->month && $this->year) {
        //     $this->hasHistoricalData = $this->aiService->hasEnoughHistoricalData($this->category_id);
        // }
        $this->aiService = new BudgetAiService($this->category_id ?: null, Auth::id(), $this->month, $this->year);
        if ($this->month && $this->year) {
            $this->hasHistoricalData = $this->aiService->hasEnoughHistoricalData();
        }
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
