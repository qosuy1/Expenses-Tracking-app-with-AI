<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Expense extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'title',
        'description',
        'date',
        'recurring_expense_id',
        'is_auto_generated'
    ];

    protected $casts = [
        'date' => 'date',
        'is_auto_generated' => 'boolean',
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function recurringExpense()
    {
        return $this->belongsTo(RecurringExpense::class, 'recurring_expense_id');
    }

    // #[Scope]
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    // #[Scope]
    public function scopeRecurring($query)
    {
        return $query->whereNotNull('recurring_expense_id');
    }
    // #[Scope]
    public function scopeOneTime($query)
    {
        return $query->where('recurring_expense_id', null);
    }

    //  #[Scope]
    public function scopeInMonth($query, $month, $year)
    {
        return $query->whereMonth('date', $month)->whereYear('date', $year);
    }

    // #[Scope]
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('date', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ]);
    }
    public function scopeThisYear($query)
    {
        return $query->whereYear('date', now()->year);
    }


    public function isRecurring()
    {
        return $this->recurring_expense_id !== null || $this->is_auto_generated;
    }

    public function formattedAmount()
    {
        return number_format($this->amount, 2);
    }

}
